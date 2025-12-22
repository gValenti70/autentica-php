<?php
session_start();

// ===============================
// AUTH
// ===============================
if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=autentica_admin.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

    if (!$resp) return [];
    return json_decode($resp, true) ?: [];
}

// ===============================
// FILTRI
// ===============================
$search = $_GET['search'] ?? "";
$stato  = $_GET['stato'] ?? "";

// ===============================
// QUERY BACKEND
// ===============================
$query = http_build_query(array_filter([
    "search" => $search,
    "stato"  => $stato
]));

$rows = backend_get("$API_BASE/admin/analisi?$query");
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Autentica – Dashboard Analisi</title>
<link rel="icon" type="image/png" href="images/autentica.png">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --adm-blue: #003b70;
    --adm-blue-light: #e6ecf7;
    --adm-blue-soft: #e7edf5;
    --adm-bg: #f5f6fa;
    --adm-border: #d0d7e2;
    --adm-text: #243447;
    --adm-gray: #6b7480;
}

* { font-family: "Titillium Web", system-ui, sans-serif; }

body {
    background: var(--adm-bg);
    color: var(--adm-text);
}

.adm-topbar {
    background: var(--adm-blue);
    color: #fff;
    padding: .35rem 0;
    font-size: .78rem;
}

.adm-header {
    background: #fff;
    border-bottom: 1px solid var(--adm-border);
}

.adm-header-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .5rem 0;
}

.adm-logo {
    display: flex;
    align-items: center;
    gap: .5rem;
}

.adm-logo-symbol {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--adm-blue), var(--adm-blue-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
}

.adm-logo-text span:first-child {
    font-size: .85rem;
    font-weight: 700;
    color: var(--adm-blue);
}

.adm-logo-text span:last-child {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #808894;
}

.filter-box {
    background: #fff;
    border: 1px solid var(--adm-border);
    padding: 1rem;
    border-radius: 8px;
}

.table thead {
    background: var(--adm-blue-soft);
}

.badge-lg {
    font-size: .9rem;
    padding: .45em .8em;
}

.btn-adm-primary {
    background: var(--adm-blue);
    border-color: var(--adm-blue);
    color: #ffffffff;
    font-weight: 600;
}

.btn-adm-primary:hover {
    background: #002e63;
    border-color: #002e63;
    color: white;
}
.btn-adm-secondary { background:#e6ecf7;border-color:#003b80;color:#003b80;font-weight:600; }

</style>

</head>

<body>

<!-- TOP BAR -->
<div class="adm-topbar">
    <div class="container d-flex justify-content-between">
        <span>Portale Autentica – Area Riservata</span>
        <span>Utente: <strong><?= htmlentities($user_id) ?></strong></span>
    </div>
</div>

<!-- HEADER -->
<header class="adm-header">
    <div class="container adm-header-inner">
        <div class="adm-logo">
            <div class="adm-logo-symbol">ADM</div>
            <div class="adm-logo-text">
                <span>Autentica</span>
                <span>dashboard analisi</span>
            </div>
        </div>
        <a href="autentica.php?reset=1" class="btn btn-adm-secondary">⬅ Torna all'app</a>
    </div>
</header>

<div class="container py-4">

<h3 class="mb-3" style="color:var(--adm-blue); font-weight:700;">📊 Dashboard Analisi</h3>

<!-- FILTRI -->
<div class="filter-box mb-4">
<form method="GET" class="row g-3">
    <div class="col-md-4">
        <input type="text" name="search" value="<?= htmlentities($search) ?>"
               class="form-control" placeholder="Cerca ID, utente, marca, modello">
    </div>

    <div class="col-md-3">
        <select name="stato" class="form-control">
            <option value="">Tutti gli stati</option>
            <option value="in_corso"   <?= $stato=="in_corso" ? "selected" : "" ?>>In corso</option>
            <option value="completata" <?= $stato=="completata" ? "selected" : "" ?>>Completata</option>
        </select>
    </div>

    <div class="col-md-2">
        <button class="btn btn-adm-primary w-100">Filtra</button>
    </div>

    <div class="col-md-2">
        <a href="autentica_admin.php" class="btn btn-secondary w-100">Reset</a>
    </div>
</form>
</div>

<!-- TABLE -->
<div class="card shadow-sm">
<div class="card-body">

<table class="table table-bordered table-hover align-middle">
<thead>
<tr>
    <th>ID</th>
    <th>Utente</th>
    <th>Stato</th>
    <th>Marca</th>
    <th>Modello</th>
    <th>Foto</th>
    <th>Step</th>
    <th>%</th>
    <th>Data</th>
    <th></th>
</tr>
</thead>
<tbody>

<?php if (!$rows): ?>
<tr>
    <td colspan="10" class="text-center text-muted">Nessuna analisi trovata</td>
</tr>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlentities($r['id']) ?></td>
    <td><?= htmlentities($r['user_id']) ?></td>

    <td>
        <?php if ($r['stato'] === 'completata'): ?>
            <span class="badge bg-success">Completata</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">In corso</span>
        <?php endif; ?>
    </td>

    <td><?= htmlentities($r['marca_stimata']) ?></td>
    <td><?= htmlentities($r['modello_stimato']) ?></td>

    <td class="text-center">
        <span class="badge bg-info text-dark"><?= intval($r['totale_foto']) ?> foto</span>
    </td>

    <td class="text-center"><strong><?= intval($r['last_step']) ?></strong></td>

    <td class="text-center">
        <?php
        $p = $r['percentuale_contraffazione'];
        if (is_numeric($p)) {
            if ($p < 33) echo "<span class='badge bg-success badge-lg'>{$p}%</span>";
            elseif ($p < 70) echo "<span class='badge bg-warning text-dark badge-lg'>{$p}%</span>";
            else echo "<span class='badge bg-danger badge-lg'>{$p}%</span>";
        } else {
            echo "<span class='badge bg-secondary'>N.D.</span>";
        }
        ?>
    </td>

    <td><?= htmlentities($r['created_at']) ?></td>

    <td class="text-center">
        <a href="autentica_admin_dettaglio.php?id=<?= urlencode($r['id']) ?>"
           class="btn btn-sm btn-adm-primary">
            Apri
        </a>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>
</div>

</div>
</body>
</html>

