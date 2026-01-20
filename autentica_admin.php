
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

// ===============================
// ENV / API
// ===============================
function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
$API_BASE = env('API_BASE', 'https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net');

function backend_get(string $url): array {
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
// INPUT: FILTRI + PAGINAZIONE
// ===============================
$search = $_GET['search'] ?? "";
$stato  = $_GET['stato'] ?? "";

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$page_size = isset($_GET['page_size']) ? (int)$_GET['page_size'] : 25;
$allowedSizes = [10, 25, 50, 100];
if (!in_array($page_size, $allowedSizes, true)) $page_size = 25;

// ===============================
// CALL BACKEND
// ===============================
$query = http_build_query(array_filter([
    "search"    => $search,
    "stato"     => $stato,
    "page"      => $page,
    "page_size" => $page_size
]));

$resp = backend_get("$API_BASE/admin/analisi?$query");

// Supporto backend nuovo (object con items/meta) + fallback backend vecchio (array puro)
if (isset($resp[0]) && is_array($resp)) {
    $rows = $resp;
    $total = count($rows);
    $total_pages = 1;
} else {
    $rows        = $resp['items'] ?? [];
    $total       = (int)($resp['total'] ?? 0);
    $page        = (int)($resp['page'] ?? $page);
    $page_size   = (int)($resp['page_size'] ?? $page_size);
    $total_pages = (int)($resp['total_pages'] ?? 1);
    if ($total_pages < 1) $total_pages = 1;
    if ($page < 1) $page = 1;
    if ($page > $total_pages) $page = $total_pages;
}

// ===============================
// HELPERS URL (mantieni filtri)
// ===============================
function build_url(array $override = []): string {
    $qs = array_merge($_GET, $override);
    return '?' . http_build_query($qs);
}

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

.btn-adm-secondary {
    background: #e6ecf7;
    border-color: #003b80;
    color: #003b80;
    font-weight: 600;
}

.pager-meta {
    font-size: .9rem;
    color: #6b7480;
}
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
        <select name="page_size" class="form-control">
            <?php foreach ([10,25,50,100] as $s): ?>
                <option value="<?= $s ?>" <?= ($page_size==$s) ? "selected" : "" ?>><?= $s ?>/pag</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-1">
        <button class="btn btn-adm-primary w-100">Filtra</button>
    </div>

    <div class="col-md-2">
        <a href="autentica_admin.php" class="btn btn-secondary w-100">Reset</a>
    </div>

    <!-- quando filtri, riparti da pagina 1 -->
    <input type="hidden" name="page" value="1">
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
    <th>Tipologia</th>
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
    <td colspan="11" class="text-center text-muted">Nessuna analisi trovata</td>
</tr>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlentities($r['id'] ?? "") ?></td>
    <td><?= htmlentities($r['user_id'] ?? "") ?></td>

    <td>
        <?php if (($r['stato'] ?? "") === 'completata'): ?>
            <span class="badge bg-success">Completata</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">In corso</span>
        <?php endif; ?>
    </td>

    <td><?= htmlentities($r['marca_stimata'] ?? "") ?></td>
    <td><?= htmlentities($r['modello_stimato'] ?? "") ?></td>
    <td><?= htmlentities($r['tipologia'] ?? "") ?></td>

    <td class="text-center">
        <span class="badge bg-info text-dark"><?= intval($r['totale_foto'] ?? 0) ?> foto</span>
    </td>

    <td class="text-center"><strong><?= intval($r['last_step'] ?? 1) ?></strong></td>

    <td class="text-center">
        <?php
        $p = $r['percentuale_contraffazione'] ?? null;
        if (is_numeric($p)) {
            $p = (float)$p;
            $p_txt = rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.'); // 12.0 -> 12
            if ($p < 35) echo "<span class='badge bg-success badge-lg'>{$p_txt}%</span>";
            elseif ($p < 66) echo "<span class='badge bg-warning text-dark badge-lg'>{$p_txt}%</span>";
            else echo "<span class='badge bg-danger badge-lg'>{$p_txt}%</span>";
        } else {
            echo "<span class='badge bg-secondary'>N.D.</span>";
        }
        ?>
    </td>

    <td><?= htmlentities($r['created_at'] ?? "") ?></td>

    <td class="text-center">
        <a href="autentica_admin_dettaglio.php?id=<?= urlencode($r['id'] ?? "") ?>"
           class="btn btn-sm btn-adm-primary">
            Apri
        </a>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<?php
// ===============================
// PAGINAZIONE UI (Bootstrap)
// ===============================
if ($total_pages < 1) $total_pages = 1;

$window = 2;
$start = max(1, $page - $window);
$end   = min($total_pages, $page + $window);

// Per mostrare sempre anche prima/ultima con ellissi
$showEllipsisLeft  = ($start > 2);
$showEllipsisRight = ($end < $total_pages - 1);
?>

<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="pager-meta">
        Totale: <strong><?= intval($total) ?></strong>
        — Pagina <strong><?= intval($page) ?></strong> / <strong><?= intval($total_pages) ?></strong>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Paginazione analisi">
        <ul class="pagination mb-0">

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page <= 1 ? '#' : build_url(['page' => 1]) ?>">«</a>
            </li>

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page <= 1 ? '#' : build_url(['page' => $page - 1]) ?>">‹</a>
            </li>

            <!-- Prima pagina -->
            <?php if ($start > 1): ?>
                <li class="page-item <?= 1 == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= build_url(['page' => 1]) ?>">1</a>
                </li>
            <?php endif; ?>

            <?php if ($showEllipsisLeft): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>

            <!-- Finestra centrale -->
            <?php for ($p = $start; $p <= $end; $p++): ?>
                <?php if ($p == 1 || $p == $total_pages) continue; ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= build_url(['page' => $p]) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($showEllipsisRight): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>

            <!-- Ultima pagina -->
            <?php if ($total_pages > 1 && $end < $total_pages): ?>
                <li class="page-item <?= $total_pages == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= build_url(['page' => $total_pages]) ?>"><?= $total_pages ?></a>
                </li>
            <?php endif; ?>

            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page >= $total_pages ? '#' : build_url(['page' => $page + 1]) ?>">›</a>
            </li>

            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page >= $total_pages ? '#' : build_url(['page' => $total_pages]) ?>">»</a>
            </li>

        </ul>
    </nav>
    <?php endif; ?>
</div>

</div>
</div>

</div>
</body>
</html>



