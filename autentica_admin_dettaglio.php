<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=autentica_admin.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("ID mancante");
}

$id = $_GET['id'];

function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
$API_BASE = env('API_BASE', 'https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net');


function backend_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    return json_decode($resp, true);
}

$data = backend_get("$API_BASE/admin/analisi/" . urlencode($id));

if (!$data || !isset($data['analisi'])) {
    die("Analisi non trovata");
}

$analisi = $data['analisi'];
$foto = $data['foto'];

$perc = $analisi["percentuale_contraffazione"];

function stile_card($perc) {

    // Caso speciale: analisi impossibile
    if ($perc === 100 || $perc === "100") {
        return [
            "bg"     => "#fdecea",
            "border" => "#dc3545",
            "label"  => "❌ ANALISI IMPOSSIBILE"
        ];
    }

    if ($perc === null) {
        return [
            "bg"=>"#f8f9fa",
            "border"=>"#6c757d",
            "label"=>"⚪ Non disponibile"
        ];
    }

    if ($perc <= 35) {
        return [
            "bg"=>"#e8f7ee",
            "border"=>"#28a745",
            "label"=>"🟢 Probabilità di contraffazione bassa"
        ];
    }

    if ($perc <= 66) {
        return [
            "bg"=>"#fff8e1",
            "border"=>"#ffc107",
            "label"=>"🟡 Rischio moderato"
        ];
    }

    return [
        "bg"=>"#fdecea",
        "border"=>"#dc3545",
        "label"=>"🔴 Probabilità di contraffazione alta"
    ];
}

$style = stile_card($perc);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Dettaglio Analisi #<?= htmlentities($analisi['id']) ?></title>
<link rel="icon" type="image/png" href="images/autentica.png">
<link href="https://fastly.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; }
.header-adm { background:#003b80;color:#fff;padding:18px 24px;border-radius:6px;margin-bottom:30px; }
.btn-adm-primary { background:#003b80;border-color:#003b80;color:#fff;font-weight:600; }
.btn-adm-secondary { background:#e6ecf7;border-color:#003b80;color:#003b80;font-weight:600; }
.card-header { background:#e6ecf7;font-weight:600; }
.json-box { background:#1e1e1e;color:#fff;font-size:13px;border-radius:8px;padding:14px;max-height:400px;overflow:auto; }
.img-step { max-height:300px;border-radius:10px;border:2px solid #ddd; }
</style>

</head>

<body>
<div class="container py-4">

<div class="header-adm">
    <h2 class="m-0">🔍 Dettaglio Analisi #<?= htmlentities($analisi['id']) ?></h2>
    <span>Utente: <strong><?= htmlentities($analisi['user_id']) ?></strong></span>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="autentica_admin.php" class="btn btn-adm-secondary">⬅ Dashboard</a>
    <a href="autentica.php?reset=1" class="btn btn-adm-secondary">⬅ App</a>
</div>

<div class="card shadow-sm mb-4"
     style="background:<?= $style['bg'] ?>;border-left:6px solid <?= $style['border'] ?>;">
<div class="card-header">📌 Informazioni Generali</div>
<div class="card-body">

<div class="mb-3 p-2"
     style="background:#fff;border-radius:8px;border:2px solid <?= $style['border'] ?>;display:inline-block;">
<strong><?= $style['label'] ?></strong>
</div>

<p><strong>Stato:</strong>
<?= $analisi["stato"]=="completata"
    ? "<span class='badge bg-success'>Completata</span>"
    : "<span class='badge bg-warning text-dark'>In corso</span>" ?>
</p>

<p><strong>Marca:</strong> <?= htmlentities($analisi["marca_stimata"]) ?></p>
<p><strong>Modello:</strong> <?= htmlentities($analisi["modello_stimato"]) ?></p>

<p><strong>Percentuale:</strong>
<?= is_numeric($perc)
    ? "<span class='badge bg-dark'>{$perc}%</span>"
    : "<span class='badge bg-secondary'>N.D.</span>" ?>
</p>

<p><strong>Giudizio finale:</strong><br>
<?= nl2br(htmlentities($analisi["giudizio_finale"])) ?>
</p>

</div>
</div>

<h4 class="mb-3">📸 Foto e JSON step-by-step</h4>

<?php foreach ($foto as $f): ?>
<?php $pretty = json_encode($f["json_response"], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); ?>

<div class="card shadow-sm mb-4">
<div class="card-header">Step <?= intval($f["step"]) ?></div>
<div class="card-body">

<img src="data:image/jpeg;base64,<?= $f["foto_base64"] ?>" class="img-step mb-3">

<button class="btn btn-adm-primary btn-sm mb-2"
        data-bs-toggle="collapse"
        data-bs-target="#json<?= $f["id"] ?>">
📄 Mostra JSON
</button>

<div id="json<?= $f["id"] ?>" class="collapse">
<pre class="json-box"><?= htmlentities($pretty) ?></pre>
</div>

<p class="text-muted mt-2"><small>Creato il: <?= htmlentities($f["created_at"]) ?></small></p>

</div>
</div>
<?php endforeach; ?>

</div>

<script src="https://fastly.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






