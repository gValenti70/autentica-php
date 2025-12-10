<?php
session_start();
if (!isset($_GET['id'])) {
    die("ID mancante");
}

$id = intval($_GET['id']);

$mysqli = new mysqli("localhost", "root", "", "autentica");
if ($mysqli->connect_errno) {
    die("Errore connessione DB");
}

// INFO ANALISI
$res = $mysqli->query("SELECT * FROM analisi WHERE id = $id");
$analisi = $res->fetch_assoc();

if (!$analisi) {
    die("Analisi non trovata");
}

// FOTO + JSON
$sql = "SELECT * FROM analisi_foto WHERE id_analisi = $id ORDER BY step ASC";
$foto_res = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Autentica – Dettaglio Analisi #<?php echo $id; ?></title>
<link rel="icon" type="image/png" href="images/autentica.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --adm-blue: #003b80;
    --adm-blue-light: #e6ecf7;
    --adm-dark: #1b1b1b;
}

body {
    background: #f4f6f9;
}

.header-adm {
    background: var(--adm-blue);
    color: white;
    padding: 18px 24px;
    border-radius: 6px;
    margin-bottom: 30px;
}

.btn-adm-primary {
    background: var(--adm-blue);
    border-color: var(--adm-blue);
    font-weight: 600;
    color: white;
}

.btn-adm-primary:hover {
    background: #002266;
    border-color: #002266;
    color:white;
}

.btn-adm-secondary {
    background: var(--adm-blue-light);
    border-color: var(--adm-blue);
    color: var(--adm-blue);
    font-weight: 600;
}

.btn-adm-secondary:hover {
    background: #d9e3f7;
    color: var(--adm-blue);
}

.card {
    border: 1px solid #d9e3f7;
}

.card-header {
    background: var(--adm-blue-light);
    font-weight: 600;
}

.json-box {
    background: #1e1e1e;
    color: #fff;
    font-size: 13px;
    border-radius: 8px;
    padding: 14px;
    max-height: 400px;
    overflow:auto;
}

.img-step {
    max-height: 300px;
    border-radius: 10px;
    border: 2px solid #ddd;
}
</style>
</head>

<body>

<div class="container py-4">

    <!-- HEADER ADM -->
    <div class="header-adm mb-4">
        <h2 class="m-0">🔍 Dettaglio Analisi #<?php echo $id; ?></h2>
        <span>Utente: <strong><?= htmlentities($_SESSION['user_id']) ?></strong></span>
    </div>

    <!-- BOTTONI -->
    <div class="d-flex gap-2 mb-4">
        <a href="autentica_admin.php" class="btn btn-adm-secondary">⬅ Torna alla Dashboard</a>
        <a href="autentica.php?reset=1" class="btn btn-adm-secondary">⬅ Torna all'app</a>
    </div>

    <?php
    $perc = $analisi["percentuale_contraffazione"];

    function stile_card($perc) {
        if ($perc === null) {
            return [
                "bg" => "#f8f9fa",
                "border" => "#6c757d",
                "label" => "⚪ Non disponibile"
            ];
        }

        if ($perc <= 33) {
            return [
                "bg" => "#e8f7ee",
                "border" => "#28a745",
                "label" => "🟢 Bassa contraffazione"
            ];
        } elseif ($perc <= 66) {
            return [
                "bg" => "#fff8e1",
                "border" => "#ffc107",
                "label" => "🟡 Rischio moderato"
            ];
        } else {
            return [
                "bg" => "#fdecea",
                "border" => "#dc3545",
                "label" => "🔴 Alta contraffazione"
            ];
        }
    }

    $style = stile_card($perc);
    ?>

    <!-- CARD INFO FINALI -->
<!-- CARD INFO FINALI (DINAMICA) -->
<div class="card shadow-sm mb-4" 
     style="background: <?= $style['bg'] ?>; border-left: 6px solid <?= $style['border'] ?>;">

    <div class="card-header" style="border-bottom: 1px solid <?= $style['border'] ?>;">
        📌 Informazioni Generali
    </div>

    <div class="card-body">

        <!-- Badge semaforico -->
        <div class="mb-3 p-2" style="
            background: white; 
            border-radius: 8px; 
            border: 2px solid <?= $style['border'] ?>;
            display: inline-block;
            font-size: 18px;">
            <strong><?= $style["label"] ?></strong>
        </div>

        <p><strong>Utente:</strong> <?= htmlspecialchars($analisi["user_id"]) ?></p>

        <p><strong>Stato:</strong> 
            <?php if ($analisi["stato"] == "completata"): ?>
                <span class="badge bg-success">Completata</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark">In corso</span>
            <?php endif; ?>
        </p>

        <p><strong>Marca stimata:</strong> <?= htmlspecialchars($analisi["marca_stimata"]); ?></p>
        <p><strong>Modello stimato:</strong> <?= htmlspecialchars($analisi["modello_stimato"]); ?></p>

        <p><strong>Percentuale:</strong><br>
            <?php if ($perc === null): ?>
                <span class="badge bg-secondary">N.D.</span>
            <?php elseif ($perc <= 33): ?>
                <span class="badge" style="background:#28a745;"><?= $perc ?>%</span>
            <?php elseif ($perc <= 66): ?>
                <span class="badge" style="background:#ffc107; color:#000;"><?= $perc ?>%</span>
            <?php else: ?>
                <span class="badge" style="background:#dc3545;"><?= $perc ?>%</span>
            <?php endif; ?>
        </p>

        <p><strong>Giudizio finale:</strong><br>
            <span style="font-size: 26px; margin-right: 10px;">
                <?php
                    if ($perc === null) echo "⚪";
                    elseif ($perc <= 33) echo "🟢";
                    elseif ($perc <= 66) echo "🟡";
                    else echo "🔴";
                ?>
            </span>
            <?= nl2br(htmlspecialchars($analisi["giudizio_finale"])); ?>
        </p>

    </div>
</div>



    <!-- FOTO + JSON -->
    <h4 class="mb-3">📸 Foto e JSON step-by-step</h4>

    <?php while ($f = $foto_res->fetch_assoc()): ?>
        <?php 
            $json = $f["json_response"] ?: "{}";
            $pretty = json_encode(json_decode($json, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Step <?= $f["step"] ?>
            </div>

            <div class="card-body">

                <!-- FOTO -->
                <img 
                    src="data:image/jpeg;base64,<?php echo $f["foto_base64"]; ?>"
                    class="img-step mb-3"
                >

                <!-- JSON BUTTON -->
                <button 
                    class="btn btn-adm-primary btn-sm mb-2"
                    data-bs-toggle="collapse"
                    data-bs-target="#json<?= $f["id"]; ?>">
                    📄 Mostra JSON
                </button>

                <!-- JSON BOX -->
                <div id="json<?= $f["id"]; ?>" class="collapse">
                    <pre class="json-box"><?= htmlspecialchars($pretty); ?></pre>
                </div>

                <p class="text-muted mt-2">
                    <small>Creato il: <?= $f["created_at"]; ?></small>
                </p>

            </div>
        </div>

    <?php endwhile; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
