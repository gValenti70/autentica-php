<?php
session_start();

// =============================================
// CONFIG
// =============================================
$max_steps = 7;
$API_BASE = "autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net";

// =============================================
// LOGIN CHECK
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=autentica_azure.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// =============================================
// INIT SESSION VARS
// =============================================
if (!isset($_SESSION['step']))        $_SESSION['step'] = 1;
if (!isset($_SESSION['id_analisi']))  $_SESSION['id_analisi'] = null;
if (!isset($_SESSION['last_api']))    $_SESSION['last_api'] = null;
if (!isset($_SESSION['immagini']))    $_SESSION['immagini'] = [];

$step         = $_SESSION['step'];
$id_analisi   = $_SESSION['id_analisi'];
$risposta_api = $_SESSION['last_api'];

// ======================================================
// 📥 RECUPERO TUTTI I JSON DI TUTTI GLI STEP DAL DB
// ======================================================


$storico_json = [];
$immagini = [];
$analisi_backend = null;

if ($id_analisi) {

    $ch = curl_init("$API_BASE/stato-analisi/$id_analisi");
    curl_setopt_array($ch, [ CURLOPT_RETURNTRANSFER => true ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data) {
        $analisi_backend = $data["analisi"];
        $immagini = $data["immagini_base64"];
        $storico_json = $data["foto"]; // ogni elemento ha step + json_response
        $risposta_api = $data["ultimo_json"]; // sostituisce il tuo last_api
    }
}


// =============================================
// RESET ANALISI
// =============================================
if (isset($_GET['reset'])) {

    $uid = $_SESSION['user_id'];

    session_unset();
    session_destroy();

    session_start();
    $_SESSION['user_id'] = $uid;

    header("Location: autentica_azure.php");
    exit;
}


// =============================================
// RIPRESA ANALISI – VIA GET id_analisi
// =============================================
if (isset($_GET['riprendi'])) {

    $id = intval($_GET['riprendi']);
    $_SESSION['id_analisi'] = $id;

    // chiamata backend
    $ch = curl_init("$API_BASE/stato-analisi/$id");
    curl_setopt_array($ch, [ CURLOPT_RETURNTRANSFER => true ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (is_array($json)) {

        // STEP CORRETTO
        if (isset($json["stato"]) && $json["stato"] === "completata") {
            $_SESSION['step'] = 99;
        }
        elseif (isset($json["ultimo_json"]["richiedi_altra_foto"]) &&
                $json["ultimo_json"]["richiedi_altra_foto"] === false) {
            $_SESSION['step'] = 99;
        }
        else {
            $_SESSION['step'] = $json["step_corrente"];
        }

        // FOTO DAL DB
        $_SESSION['immagini'] = $json["immagini_base64"];

        // ULTIMO JSON
        $_SESSION["last_api"] = $json["ultimo_json"] ?? null;
    }

    header("Location: autentica_azure.php");
    exit;
}


// =============================================
// INVIO FOTO
// =============================================
$errore_api = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {

    function toBase64($f) {
        return base64_encode(file_get_contents($f));
    }

    $foto_b64 = toBase64($_FILES['foto']['tmp_name']);
    $_SESSION['immagini'][] = $foto_b64;

    // payload
    $payload = [
        "user_id"    => $user_id,
        "foto"       => $foto_b64,
        "id_analisi" => $id_analisi,
    ];

    $ch = curl_init("$API_BASE/analizza-oggetto");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (!is_array($json)) {
        $errore_api = "<pre>Errore API:\n$response</pre>";
    } else {

        // salva id_analisi se è la prima volta
        if (!$_SESSION['id_analisi']) {
            $_SESSION['id_analisi'] = $json["id_analisi"];
        }

        $_SESSION['last_api'] = $json;

        // next or end
        if (!empty($json["richiedi_altra_foto"])) {
            $_SESSION['step']++;
        } else {
            $_SESSION['step'] = 99;
        }

        header("Location: autentica_azure.php");
        exit;
    }
}

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

<!-- Titillium Web (simile ADM) -->
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

* {
    font-family: "Titillium Web", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

body {
    background: var(--adm-bg);
    color: var(--adm-text);
    margin: 0;
}

/* HEADER ADM */

.adm-topbar {
    background: var(--adm-blue);
    color: #fff;
    padding: 0.35rem 0;
    font-size: 0.78rem;
}

.adm-topbar .small-text {
    opacity: 0.9;
}

.adm-header {
    background: #ffffff;
    border-bottom: 1px solid var(--adm-border);
}

.adm-header-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.55rem 0;
    gap: 0.75rem;
}

/* Logo A2 simbolico */
.adm-logo {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.adm-logo-symbol {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--adm-blue) 0%, var(--adm-blue-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.03em;
}

.adm-logo-text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.adm-logo-text span:first-child {
    font-size: 0.80rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--adm-blue);
}

.adm-logo-text span:last-child {
    font-size: 0.70rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #808894;
}

/* Right side info */
.adm-header-right {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
    align-items: center;
    justify-content: flex-end;
}

.adm-badge-app {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--adm-blue-soft);
    color: var(--adm-blue);
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    border: 1px solid #d2d8e3;
}

.adm-user-tag {
    font-size: 0.75rem;
    color: #5a6473;
}

/* Breadcrumb */

.adm-breadcrumb {
    background: #ffffff;
    border-bottom: 1px solid var(--adm-border);
}

.adm-breadcrumb-inner {
    padding: 0.55rem 0;
    font-size: 0.78rem;
    color: #6b7480;
}

.adm-breadcrumb a {
    color: var(--adm-blue-light);
    text-decoration: none;
}

.adm-breadcrumb a:hover {
    text-decoration: underline;
}

/* Layout principale */

.adm-main {
    padding-top: 1.75rem;
    padding-bottom: 2.5rem;
}

/* Card analisi */

.adm-card {
    border-radius: 10px;
    border: 1px solid var(--adm-border);
}

.adm-card-header {
    border-bottom: 1px solid var(--adm-border);
    padding-bottom: 0.65rem;
    margin-bottom: 0.75rem;
}

.adm-card-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--adm-blue);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.adm-card-subtitle {
    font-size: 0.78rem;
    color: #7b8593;
}

/* Bottoni */

.btn-adm-primary {
    background: var(--adm-blue);
    border-color: var(--adm-blue);
    color: #ffffff !important;     /* ← testo bianco */
    font-weight: 600;
    font-size: 0.9rem;
}

.btn-adm-primary:hover {
    background: var(--adm-blue-light);
    border-color: var(--adm-blue-light);
    color: #ffffff !important;     /* ← testo bianco anche su hover */
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


.btn-adm-outline {
    border-radius: 999px;
    font-size: 0.78rem;
    padding: 0.25rem 0.75rem;
}

/* thumbs */

.thumb {
    height: 80px;
    border-radius: 6px;
    border: 1px solid #ccd3df;
    box-shadow: 0 0 3px rgba(0,0,0,0.03);
    object-fit: cover;
}

#preview-img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 10px;
    border: 1px solid #ccd3df;
}

.json-box {
    max-height: 400px;
    overflow: auto;
}

/* Spinner */

#loading-spinner p {
    font-size: 0.85rem;
}

/* Badge percentuale risultato */

.badge-result {
    font-size: 1rem;
    padding: 0.45rem 0.9rem;
}

/* Sezione JSON storico */

.json-step-header {
    font-size: 0.80rem;
    font-weight: 600;
    color: #4c5663;
}

/* Piccole rifiniture */

.form-label {
    font-weight: 500;
    font-size: 0.9rem;
}

.alert {
    font-size: 0.9rem;
    border-radius: 8px;
}

/* Responsive */

@media (max-width: 576px) {
    .adm-header-inner {
        flex-direction: column;
        align-items: flex-start;
    }
    .adm-header-right {
        justify-content: flex-start;
    }
}
</style>
</head>

<body>

<!-- TOP BAR ADM -->
<div class="adm-topbar">
    <div class="container d-flex justify-content-between">
        <span>Portale Autentica – Area Riservata</span>
        <span>Utente: <strong><?= htmlentities($_SESSION['user_id']) ?></strong></span>
    </div>
</div>

<!-- HEADER ADM -->
<header class="adm-header">
    <div class="container adm-header-inner">

        <!-- Logo simbolico ADM (A2) -->
        <div class="adm-logo">
            <div class="adm-logo-symbol">
                ADM
            </div>
            <div class="adm-logo-text">
                <span>Autentica</span>
                <span>verifica oggetti di valore</span>
            </div>
        </div>

        <!-- Info lato destro -->
        <div class="adm-header-right">
            <div class="d-flex" style="gap:0.35rem;">
                <a href="autentica_prompt.php" class="btn btn-sm btn-adm-secondary">✏️ Prompt</a>
                <a href="autentica_admin.php" class="btn btn-sm btn-adm-secondary">📊 Admin</a>
            </div>
        </div>

    </div>
</header>


<!-- MAIN -->
<main class="adm-main">
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-8 col-md-10">

<div class="card adm-card shadow-sm">
<div class="card-body p-4">

    <div class="adm-card-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <?php if ($step>1): ?>
            <div class="adm-card-title">
                <span class="adm-badge-app">
                🔍 Analisi di <strong><?= htmlentities($risposta_api["modello_stimato"]) ?> - <?= htmlentities($risposta_api["marca_stimata"]) ?></strong>
                </span>
            </div>
            <?php else: ?>
            <div class="adm-card-title">
                <span class="adm-badge-app">
                    🔍 Analisi di un nuovo oggetto
                </span>
            </div>
            <?php endif; ?>            
                <div class="adm-card-subtitle">
                    Carica fino a 7 immagini per ricevere una valutazione guidata.
                </div>
            </div>
            <!-- Step / stato -->
            <?php if ($step != 99): ?>
                <span class="badge bg-light text-muted">
                    Step corrente: <strong><?= $step ?></strong> <!--/ <? $max_steps ?>-->
                </span>
            <?php else: ?>
                <span class="badge bg-success text-white">
                    Analisi completata
                </span>
            <?php endif; ?>
    </div>

    <?php if ($errore_api): ?>
        <div class="alert alert-danger mt-2"><?= $errore_api ?></div>
    <?php endif; ?>

    <!-- 1) FOTO CARICATE -->
    <?php if (!empty($_SESSION['immagini'])): ?>
    <div class="mb-4 mt-1">
        <label class="form-label"><strong>📸 Foto caricate</strong></label>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach($_SESSION['immagini'] as $img): ?>
                <img src="data:image/jpeg;base64,<?= $img ?>" class="thumb" alt="Foto caricata">
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- 2) MARCA + MODELLO
    <php if ($risposta_api && ($step != 1)): ?>
    <div class="alert alert-success text-center mb-3">
        <php if (!empty($risposta_api["marca_stimata"])): ?>
            <p class="mb-1"><strong>Marca:</strong> <= htmlentities($risposta_api["marca_stimata"]) ?></p>
        <php endif; ?>

        <php if (!empty($risposta_api["modello_stimato"])): ?>
            <p class="mb-0"><strong>Modello:</strong> <= htmlentities($risposta_api["modello_stimato"]) ?></p>
        <php endif; ?>
    </div>
    <php endif; ?>
 -->

    <!-- 3) SUGGERIMENTO FOTO SUCCESSIVA -->
    <?php if ($step != 99 && $risposta_api && !empty($risposta_api["dettaglio_richiesto"])): ?>
    <div class="alert alert-info mb-4">
        <strong>📌 Prossimo dettaglio richiesto</strong><br>
        <span><?= htmlentities($risposta_api["dettaglio_richiesto"]) ?></span>
    </div>
    <?php endif; ?>


    <!-- 4) UPLOAD FOTO -->
    <?php if ($step != 99): ?>

        <!-- SPINNER -->
        <div id="loading-spinner" class="text-center mb-3" style="display:none;">
            <div class="spinner-border text-primary" role="status" style="width: 2.75rem; height: 2.75rem;"></div>
            <p class="mt-2 text-muted mb-0">Analisi in corso, attendere...</p>
        </div>

        <form id="fotoForm" method="POST" enctype="multipart/form-data" class="mb-2">

            <label class="form-label"><strong>Carica foto <?= $step ?></strong></label>
            <input type="file" class="form-control mb-3" name="foto" required accept="image/*" onchange="previewImage(event)">

            <!-- ANTEPRIMA IMMAGINE -->
            <div id="preview-box" class="text-center mb-3" style="display:none;">
                <img id="preview-img" src="#" alt="Anteprima foto">
            </div>

            <button class="btn btn-adm-primary w-100">Invia foto</button>
        </form>
    <?php endif; ?>


    <!-- 5) GIUDIZIO FINALE -->
    <?php if ($step == 99 && $risposta_api): ?>

        <?php
        $p = $risposta_api["percentuale"] ?? null;

        if ($p === null) {
            $icon = "❓"; $badge = "bg-secondary"; $label = "N/D";
        } elseif ($p < 30) {
            $icon = "🔒"; $badge = "bg-success"; $label = "$p%";
        } elseif ($p < 70) {
            $icon = "⚠️"; $badge = "bg-warning text-dark"; $label = "$p%";
        } else {
            $icon = "❌"; $badge = "bg-danger"; $label = "$p%";
        }
        ?>

        <div class="card mt-4 shadow-sm">
            <div class="card-body text-center">
                <h5 class="mb-3">Risultato finale</h5>
                <h1 class="mb-3">
                    <?= $icon ?> 
                    <span class="badge <?= $badge ?> badge-result"><?= $label ?></span>
                </h1>

                <?php if (!empty($risposta_api["marca_stimata"])): ?>
                    <p class="mb-1"><strong>Marca:</strong> <?= htmlentities($risposta_api["marca_stimata"]) ?></p>
                <?php endif; ?>

                <?php if (!empty($risposta_api["modello_stimato"])): ?>
                    <p class="mb-2"><strong>Modello:</strong> <?= htmlentities($risposta_api["modello_stimato"]) ?></p>
                <?php endif; ?>

                <p class="mt-2 mb-0">
                    <strong>Motivazione:</strong><br>
                    <?= nl2br(htmlentities($risposta_api["motivazione"])) ?>
                </p>
            </div>
        </div>
    <?php endif; ?>


    <!-- 6) PERCENTUALE PARZIALE -->
    <?php if ($step != 99 && isset($risposta_api["percentuale"])): ?>
    <div class="alert alert-secondary text-center mt-3">
        Percentuale attuale di contraffazione stimata:<br>
        <strong><?= $risposta_api["percentuale"] ?>%</strong>
    </div>
    <?php endif; ?>


    <!-- 7) JSON COMPLETO (STORICO) -->
    <?php if (!empty($storico_json)): ?>

        <button class="btn btn-sm btn-outline-secondary w-100 mt-3"
                data-bs-toggle="collapse"
                data-bs-target="#debugJsonStorico">
            📚 Mostra JSON di tutti gli step
        </button>

        <div id="debugJsonStorico" class="collapse mt-3">

            <?php foreach ($storico_json as $row): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-header bg-light json-step-header">
                        Step <?= (int)$row['step'] ?>
                    </div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-white p-3 rounded-0 json-box" style="font-size:12px;">
<?= htmlentities(
    json_encode(
        json_decode($row['json_response'], true),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
) ?>
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
// Mostra preview immagine
function previewImage(evt) {
    const file = evt.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById("preview-img").src = e.target.result;
        document.getElementById("preview-box").style.display = "block";
    };
    reader.readAsDataURL(file);
}

// Spinner su submit (solo se il form esiste)
const form = document.getElementById("fotoForm");
if (form) {
    form.addEventListener("submit", function () {
        const spinner = document.getElementById("loading-spinner");
        if (spinner) spinner.style.display = "block";
    });
}
</script>

</body>
</html>
