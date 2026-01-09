<?php
session_start();
/**
 * =========================
 * CONFIG
 * =========================
 */
function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
$API_BASE = env('API_BASE', 'https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net');


$max_steps = 7;

// Se sei in locale:

// Se sei su Azure (decommenta quando serve):
// $API_BASE = "https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net";

$home = "autentica.php";

/**
 * =========================
 * LOGIN CHECK
 * =========================
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=" . urlencode($home));
    exit;
}

$user_id = $_SESSION['user_id'];

/**
 * =========================
 * INIT SESSION VARS
 * =========================
 */
if (!isset($_SESSION['step']))        $_SESSION['step'] = 1;
if (!isset($_SESSION['id_analisi']))  $_SESSION['id_analisi'] = null;
if (!isset($_SESSION['last_api']))    $_SESSION['last_api'] = null;
if (!isset($_SESSION['immagini']))    $_SESSION['immagini'] = [];

$step         = (int)($_SESSION['step'] ?? 1);
$id_analisi   = $_SESSION['id_analisi'] ?? null;
$risposta_api = $_SESSION['last_api'] ?? null;

$errore_api = null;

/**
 * =========================
 * HELPERS
 * =========================
 */
function safe_array($v) {
    return is_array($v) ? $v : [];
}

function curl_json_get($url, &$http_code = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $response === null || $response === "") {
        return [
            "error" => true,
            "message" => "GET fallita o risposta vuota",
            "http_code" => $http_code,
            "curl_errno" => $errno,
            "curl_error" => $err
        ];
    }

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return [
            "error" => true,
            "message" => "GET: JSON non valido",
            "http_code" => $http_code,
            "body_preview" => mb_substr($response, 0, 500)
        ];
    }

    return $json;
}

function curl_json_post($url, array $payload, &$http_code = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,           // <-- alza, GPT può sforare
        CURLOPT_CONNECTTIMEOUT => 60,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $t0 = microtime(true);
    $response = curl_exec($ch);
    $ms = round((microtime(true) - $t0) * 1000);

    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $response === null || $response === "") {
        return [
            "error" => true,
            "message" => "POST fallita o risposta vuota",
            "http_code" => $http_code,
            "curl_errno" => $errno,
            "curl_error" => $err,
            "elapsed_ms" => $ms
        ];
    }

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return [
            "error" => true,
            "message" => "POST: JSON non valido",
            "http_code" => $http_code,
            "elapsed_ms" => $ms,
            "body_preview" => mb_substr($response, 0, 1000)
        ];
    }

    // (facoltativo) aggiungi sempre elapsed_ms per debug UI
    $json["_frontend_http_code"] = $http_code;
    $json["_frontend_elapsed_ms"] = $ms;

    return $json;
}


function file_to_base64($tmp_path) {
    return base64_encode(file_get_contents($tmp_path));
}

/**
 * =========================
 * RESET ANALISI
 * =========================
 */
if (isset($_GET['reset'])) {
    $uid = $_SESSION['user_id'];
    session_unset();
    session_destroy();

    session_start();
    $_SESSION['user_id'] = $uid;

    header("Location: " . $home);
    exit;
}

/**
 * =========================
 * RIPRESA ANALISI – VIA GET riprendi
 * =========================
 */
if (isset($_GET['riprendi'])) {
    $id = trim($_GET['riprendi']);

    if (preg_match('/^[a-f0-9]{24}$/i', $id)) {

        $_SESSION['id_analisi'] = $id;

        $code = null;
        $json = curl_json_get("$API_BASE/stato-analisi/$id", $code);

        if (is_array($json)) {

            $_SESSION['last_api'] = $json["ultimo_json"] ?? null;

            if (is_array($json["immagini_base64"] ?? null)) {
                $_SESSION['immagini'] = $json["immagini_base64"];
            }

            $ultimo = safe_array($_SESSION['last_api']);
            $need_more = $ultimo["richiedi_altra_foto"] ?? true;
            $tot_foto  = count($_SESSION['immagini']);

            $_SESSION['step'] = $need_more ? max(1, $tot_foto + 1) : 99;
        }
    }

    header("Location: " . $home);
    exit;
}


/**
 * =========================
 * INVIO FOTO
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto']) && !empty($_FILES['foto']['tmp_name'])) {

    $foto_b64 = file_to_base64($_FILES['foto']['tmp_name']);

    // payload: backend si aspetta base64 "pura"
    $payload = [
        "user_id"    => $user_id,
        "foto"       => $foto_b64,
        "id_analisi" => $id_analisi,
    ];

    $code = null;
    $json = curl_json_post("$API_BASE/analizza-oggetto", $payload, $code);

    if (!is_array($json)) {
        $errore_api = "<pre>Errore API (POST /analizza-oggetto) HTTP $code\n</pre>";
    } else {

        // salva id_analisi se è la prima volta
        if (!$_SESSION['id_analisi'] && isset($json["id_analisi"])) {
            $_SESSION['id_analisi'] = $json["id_analisi"];
        }

        $_SESSION['last_api'] = $json;

        // step: se richiede altra foto -> prossimo step, altrimenti completato
        $need_more = $json["richiedi_altra_foto"] ?? true;
        $tot_foto  = $json["tot_foto"] ?? null;

        // immagini: NON fidarti della sessione, meglio ricaricare dallo stato analisi
        $new_id = $_SESSION['id_analisi'];

        // ✅ MOSTRA SUBITO LA FOTO APPENA CARICATA
        $_SESSION['immagini'][] = $foto_b64;

        // // 🔁 Poi prova ad allinearti al backend (se già aggiornato)
        // if ($new_id) {
        //     $code2 = null;
        //     $state = curl_json_get("$API_BASE/stato-analisi/$new_id", $code2);

        //     if (is_array($state)) {
        //         $imgs = $state["immagini_base64"] ?? [];
        //         if (is_array($imgs) && count($imgs) >= count($_SESSION['immagini'])) {
        //             $_SESSION['immagini'] = $imgs;
        //         }
        //     }
        // }




        if ($need_more === false || $need_more === "false" || $need_more === 0 || $need_more === "0") {
            $_SESSION['step'] = 99;
        } else {
            // prossimo step = tot_foto + 1 se disponibile, altrimenti ++
            if ($tot_foto !== null) {
                $_SESSION['step'] = max(1, (int)$tot_foto + 1);
            } else {
                $_SESSION['step'] = (int)($_SESSION['step'] ?? 1) + 1;
            }
        }

        header("Location: " . $home);
        exit;
    }
}

/**
 * ======================================================
 * 📥 RECUPERO STATO DAL BACKEND (DEFENSIVE)
 * ======================================================
 */
$storico_json = [];
$immagini = [];
$analisi_backend = null;

if (!empty($_SESSION['id_analisi'])) {

    $aid = $_SESSION['id_analisi'];
    $code = null;
    $data = curl_json_get("$API_BASE/stato-analisi/$aid", $code);


    if (is_array($data)) {
        $analisi_backend = $data["analisi"] ?? null;
        $immagini = is_array($data["immagini_base64"] ?? null) ? $data["immagini_base64"] : [];
        $storico_json = is_array($data["foto"] ?? null) ? $data["foto"] : [];
        $risposta_api = is_array($data["ultimo_json"] ?? null) ? $data["ultimo_json"] : ($data["ultimo_json"] ?? null);

        // sincronizza session immagini se backend le ha
        if (!empty($immagini)) {
            $_SESSION['immagini'] = $immagini;
        }

        // sincronizza session last_api
        if (is_array($risposta_api)) {
            $_SESSION['last_api'] = $risposta_api;
        }

        // sincronizza step (se non completata)
        if ($_SESSION['step'] != 99) {
            $ultimo = safe_array($risposta_api);
            $need_more = $ultimo["richiedi_altra_foto"] ?? true;
            $tot_foto  = $ultimo["tot_foto"] ?? count($_SESSION['immagini']);

            if ($need_more === false || $need_more === "false" || $need_more === 0 || $need_more === "0") {
                $_SESSION['step'] = 99;
            } else {
                $_SESSION['step'] = max(1, (int)$tot_foto + 1);
            }
        }

        // refresh locali
        $step = (int)($_SESSION['step'] ?? 1);
    } else {
        // se 422 o errore -> non blocca UI
        if ($code && $code >= 400) {
            // opzionale: mostrare un warning NON bloccante
            // $errore_api = "<pre>Backend /stato-analisi HTTP $code</pre>";
        }
    }
}

// fallback se backend non ha risposto
if (empty($immagini)) {
    $immagini = $_SESSION['immagini'] ?? [];
}

$risposta_api = safe_array($_SESSION['last_api'] ?? $risposta_api ?? []);

// campi “safe”
$marca  = $risposta_api["marca_stimata"] ?? null;
$modello = $risposta_api["modello_stimato"] ?? null;
$dettaglio_richiesto = $risposta_api["dettaglio_richiesto"] ?? null;
$percentuale = $risposta_api["percentuale"] ?? null;
$motivazione = $risposta_api["motivazione"] ?? null;

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
            "label"=>"🟢 Bassa contraffazione"
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
        "label"=>"🔴 Alta contraffazione"
    ];
}

$perc = $percentuale;
$style = stile_card($perc);

?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Autentica – Analisi contraffazione</title>
<link rel="icon" type="image/png" href="images/autentica.png">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Titillium Web -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap">

<style>
:root {
    --adm-blue: #003b70;
    --adm-blue-light: #e6ecf7;
    --adm-blue-soft: #e7edf5;
    --adm-bg: #f5f6fa;
    --adm-border: #d0d7e2;
    --adm-text: #243447;
}
* { font-family: "Titillium Web", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
body { background: var(--adm-bg); color: var(--adm-text); margin: 0; }

.adm-topbar { background: var(--adm-blue); color: #fff; padding: 0.35rem 0; font-size: 0.78rem; }
.adm-header { background: #ffffff; border-bottom: 1px solid var(--adm-border); }
.adm-header-inner { display:flex; justify-content:space-between; align-items:center; padding:0.55rem 0; gap:0.75rem; }

.adm-logo { display:inline-flex; align-items:center; gap:0.45rem; }
.adm-logo-symbol { width:34px; height:34px; border-radius:8px;
    background: linear-gradient(135deg, var(--adm-blue) 0%, var(--adm-blue-light) 100%);
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.85rem; letter-spacing:0.03em; }
.adm-logo-text { display:flex; flex-direction:column; line-height:1.1; }
.adm-logo-text span:first-child { font-size:0.80rem; font-weight:700; text-transform:uppercase; color: var(--adm-blue); }
.adm-logo-text span:last-child { font-size:0.70rem; text-transform:uppercase; letter-spacing:0.06em; color:#808894; }

.adm-header-right { display:flex; flex-wrap:wrap; gap:0.35rem 0.75rem; align-items:center; justify-content:flex-end; }
.adm-badge-app { font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;
    background: var(--adm-blue-soft); color: var(--adm-blue); padding:0.15rem 0.45rem; border-radius:999px; border:1px solid #d2d8e3; }

.adm-main { padding-top: 1.75rem; padding-bottom: 2.5rem; }
.adm-card { border-radius:10px; border:1px solid var(--adm-border); }
.adm-card-header { border-bottom:1px solid var(--adm-border); padding-bottom:0.65rem; margin-bottom:0.75rem; }
.adm-card-title { font-size:1.05rem; font-weight:600; color: var(--adm-blue); display:flex; align-items:center; gap:0.45rem; }
.adm-card-subtitle { font-size:0.78rem; color:#7b8593; }

.btn-adm-primary { background: var(--adm-blue); border-color: var(--adm-blue); color:#fff !important; font-weight:600; font-size:0.9rem; }
.btn-adm-primary:hover { background: #0b4e8f; border-color:#0b4e8f; color:#fff !important; }
.btn-adm-secondary { background: var(--adm-blue-light); border-color: var(--adm-blue); color: var(--adm-blue); font-weight:600; }
.btn-adm-secondary:hover { background:#d9e3f7; color: var(--adm-blue); }

.thumb { height:80px; border-radius:6px; border:1px solid #ccd3df; box-shadow:0 0 3px rgba(0,0,0,0.03); object-fit:cover; }
#preview-img { max-width:100%; max-height:300px; border-radius:10px; border:1px solid #ccd3df; }
.json-box { max-height:400px; overflow:auto; }
.badge-result { font-size:1rem; padding:0.45rem 0.9rem; }
.json-step-header { font-size:0.80rem; font-weight:600; color:#4c5663; }
.form-label { font-weight:500; font-size:0.9rem; }
.alert { font-size:0.9rem; border-radius:8px; }

@media (max-width: 576px) {
    .adm-header-inner { flex-direction:column; align-items:flex-start; }
    .adm-header-right { justify-content:flex-start; }
}
</style>
</head>

<body>

<!-- TOP BAR -->
<div class="adm-topbar">
    <div class="container d-flex justify-content-between">
        <span>Portale Autentica – Area Riservata</span>
        <span>Utente: <strong><?= htmlentities($_SESSION['user_id'] ?? '') ?></strong></span>
    </div>
</div>

<!-- HEADER -->
<header class="adm-header">
    <div class="container adm-header-inner">
        <div class="adm-logo">
            <div class="adm-logo-symbol">ADM</div>
            <div class="adm-logo-text">
                <span>Autentica</span>
                <span>verifica oggetti di valore</span>
            </div>
        </div>
        <div class="adm-header-right">
            <div class="d-flex" style="gap:0.35rem;">
                <a href="autentica_admin.php" class="btn btn-sm btn-adm-secondary">📊 Dashboard</a>
                <a href="autentica_prompt.php" class="btn btn-sm btn-adm-secondary">✏️ Prompt</a>
                <a href="autentica_vademecum.php" class="btn btn-sm btn-adm-secondary">{...} Vademecum</a>

            </div>
        </div>
    </div>
</header>

<main class="adm-main">
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-8 col-md-10">

<div class="card adm-card shadow-sm">
<div class="card-body p-4">

    <div class="adm-card-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <?php if ($step > 1 && ($marca || $modello)): ?>
                <div class="adm-card-title">
                    <span class="adm-badge-app">
                        🔍 Analisi di <strong><?= htmlentities($modello ?? "—") ?><?= $marca ? " - " . htmlentities($marca) : "" ?></strong>
                    </span>
                </div>
            <?php else: ?>
                <div class="adm-card-title">
                    <span class="adm-badge-app">🔍 Analisi di un nuovo oggetto</span>
                </div>
            <?php endif; ?>

            <div class="adm-card-subtitle">
                Carica fino a <?= (int)$max_steps ?> immagini per ricevere una valutazione guidata.
            </div>
        </div>

        <?php if ($step != 99): ?>
            <span class="badge bg-light text-muted">
                Step corrente: <strong><?= (int)$step ?></strong>
            </span>
        <?php else: ?>
            <span class="badge bg-success text-white">Analisi completata</span>
        <?php endif; ?>
    </div>

    <?php if ($errore_api): ?>
        <div class="alert alert-danger mt-2"><?= $errore_api ?></div>
    <?php endif; ?>

    <!-- FOTO CARICATE -->
    <?php 
        if (!empty($immagini) && is_array($immagini)): ?>
        <div class="mb-4 mt-1">
            <label class="form-label"><strong>📸 Foto caricate</strong></label>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($immagini as $img): ?>
                    <?php if (!empty($img)): ?>
                        <img src="data:image/jpeg;base64,<?= $img ?>" class="thumb" alt="Foto caricata">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>


    <!-- DETTAGLIO RICHIESTO -->
    <?php if ($step != 99 && !empty($dettaglio_richiesto)): ?>
        <div class="alert alert-info mb-4">
            <strong>📌 Prossimo dettaglio richiesto</strong><br>
            <span><?= htmlentities($dettaglio_richiesto) ?></span>
        </div>
    <?php endif; ?>

    <!-- UPLOAD FOTO -->
    <?php if ($step != 99): ?>

        <div id="loading-spinner" class="text-center mb-3" style="display:none;">
            <div class="spinner-border text-primary" role="status" style="width: 2.75rem; height: 2.75rem;"></div>
            <p class="mt-2 text-muted mb-0">Analisi in corso, attendere...</p>
        </div>

        <form id="fotoForm" method="POST" enctype="multipart/form-data" class="mb-2">
            <label class="form-label"><strong>Carica foto <?= (int)$step ?></strong></label>
            <input 
                type="file"
                id="fotoInput"
                class="d-none"
                name="foto"
                required
                accept="image/*"
                capture="environment"
                onchange="previewImage(event)"
            >
            
            <button 
                type="button"
                id="fotoButton"
                class="btn btn-adm-secondary w-100 mb-3">
                📁 Sfoglia file
            </button>

            <div id="preview-box" class="text-center mb-3" style="display:none;">
                <img id="preview-img" src="#" alt="Anteprima foto">
            </div>

            <button class="btn btn-adm-primary w-100">Invia foto</button>
        </form>
    <?php endif; ?>

    <!-- GIUDIZIO FINALE (CARD AUTENTICA) -->
    <?php if ($step == 99 && !empty($risposta_api)): ?>

    <div class="card shadow-sm mb-4"
        style="background:<?= $style['bg'] ?>;border-left:6px solid <?= $style['border'] ?>;">
    <div class="card-header">📌 Risultato Analisi</div>
    <div class="card-body">

    <div class="mb-3 p-2"
        style="background:#fff;border-radius:8px;border:2px solid <?= $style['border'] ?>;display:inline-block;">
    <strong><?= $style['label'] ?></strong>
    </div>

    <p><strong>Stato:</strong>
    <span class="badge bg-success">Completata</span>
    </p>

    <?php if (!empty($marca)): ?>
    <p><strong>Marca:</strong> <?= htmlentities($marca) ?></p>
    <?php endif; ?>

    <?php if (!empty($modello)): ?>
    <p><strong>Modello:</strong> <?= htmlentities($modello) ?></p>
    <?php endif; ?>

    <p><strong>Percentuale:</strong>
    <?= is_numeric($perc)
        ? "<span class='badge bg-dark'>{$perc}%</span>"
        : "<span class='badge bg-secondary'>N.D.</span>" ?>
    </p>

    <?php if (!empty($motivazione)): ?>
    <p><strong>Giudizio finale:</strong><br>
    <?= nl2br(htmlentities($motivazione)) ?>
    </p>
    <?php endif; ?>

    </div>
    </div>

    <?php endif; ?>


    <!-- PERCENTUALE PARZIALE -->
    <?php if ($step != 99 && $percentuale !== null): ?>
        <div class="alert alert-secondary text-center mt-3">
            Percentuale attuale di contraffazione stimata:<br>
            <strong><?= (int)$percentuale ?>%</strong>
        </div>
    <?php endif; ?>

    <!-- JSON STORICO -->
<?php if (is_array($storico_json) && count($storico_json) > 0): ?>

<button class="btn btn-sm btn-outline-secondary w-100 mt-3"
        data-bs-toggle="collapse"
        data-bs-target="#debugJsonStorico">
    📚 Mostra JSON di tutti gli step (<?= count($storico_json) ?>)
</button>

<div id="debugJsonStorico" class="collapse mt-3">

    <?php foreach ($storico_json as $row): ?>

        <?php
        $row = is_array($row) ? $row : [];

        $step = isset($row['step']) ? (int)$row['step'] : '—';
        $jr   = $row['json_response'] ?? null;

        if (is_array($jr)) {
            $decoded = $jr;
        } elseif (is_string($jr)) {
            $decoded = json_decode($jr, true);
            if (!is_array($decoded)) {
                $decoded = ['raw' => $jr];
            }
        } else {
            $decoded = ['raw' => $jr];
        }

        $pretty = json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        if ($pretty === false) {
            $pretty = json_encode(
                ['json_encode_error' => json_last_error_msg()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }
        ?>

        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light json-step-header">
                Step <?= $step ?>
            </div>
            <div class="card-body p-0">
                <pre class="bg-dark text-white p-3 mb-0 json-box" style="font-size:12px;">
<?= htmlentities($pretty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </pre>
            </div>
        </div>

    <?php endforeach; ?>

</div>

<?php endif; ?>




    <!-- RESET -->
    <form method="GET" class="mt-4">
        <input type="hidden" name="reset" value="1">
        <button class="btn btn-outline-secondary w-100">🔄 Nuova analisi</button>
    </form>

</div>
</div>

</div>
</div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function previewImage(evt) {
    const file = evt.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById("preview-img");
        const box = document.getElementById("preview-box");
        if (img) img.src = e.target.result;
        if (box) box.style.display = "block";
    };
    reader.readAsDataURL(file);
}

const form = document.getElementById("fotoForm");
if (form) {
    form.addEventListener("submit", function () {
        const spinner = document.getElementById("loading-spinner");
        if (spinner) spinner.style.display = "block";
    });
}
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("fotoForm");
    if (!form) return;

    form.addEventListener("submit", async function (e) {
        const fileInput = form.querySelector('input[type="file"]');
        if (!fileInput || !fileInput.files.length) return;

        e.preventDefault(); // fermiamo submit originale

        const originalFile = fileInput.files[0];

        const img = new Image();
        const reader = new FileReader();

        reader.onload = () => {
            img.onload = () => {
                const MAX_SIZE = 1280;
                let { width, height } = img;

                if (width > height && width > MAX_SIZE) {
                    height *= MAX_SIZE / width;
                    width = MAX_SIZE;
                } else if (height > MAX_SIZE) {
                    width *= MAX_SIZE / height;
                    height = MAX_SIZE;
                }

                const canvas = document.createElement("canvas");
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext("2d");
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(
                    blob => {
                        const compressedFile = new File(
                            [blob],
                            "foto.jpg",
                            { type: "image/jpeg" }
                        );

                        const dt = new DataTransfer();
                        dt.items.add(compressedFile);
                        fileInput.files = dt.files;

                        const spinner = document.getElementById("loading-spinner");
                        if (spinner) spinner.style.display = "block";

                        console.log(
                            "Original:",
                            Math.round(originalFile.size / 1024),
                            "KB → Compressed:",
                            Math.round(blob.size / 1024),
                            "KB"
                        );

                        form.submit(); // submit reale
                    },
                    "image/jpeg",
                    0.8 // qualità
                );
            };
            img.src = reader.result;
        };

        reader.readAsDataURL(originalFile);
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    const btn = document.getElementById("fotoButton");
    const input = document.getElementById("fotoInput");

    if (!btn || !input) return;

    if (isMobile) {
        btn.innerText = "📸 Scatta foto";
    }

    btn.addEventListener("click", () => {
        input.click();
    });
});
</script>

</body>
</html>








