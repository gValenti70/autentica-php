<?php
session_start();

// Controllo login
if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=autentica_admin.php");
    exit;
}

// Connessione MySQL
$mysqli = new mysqli("localhost", "root", "", "autentica");
if ($mysqli->connect_errno) {
    die("Errore MySQL: " . $mysqli->connect_error);
}

// --- FILTRI ---
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$stato = isset($_GET['stato']) ? trim($_GET['stato']) : "";

// --- QUERY ---
$sql = "
    SELECT 
        a.id,
        a.user_id,
        a.stato,
        a.step_corrente,
        a.marca_stimata,
        a.modello_stimato,
        a.percentuale_contraffazione,
        (SELECT COUNT(*) FROM analisi_foto f WHERE f.id_analisi = a.id) AS totale_foto,
        (SELECT MAX(step) FROM analisi_foto f2 WHERE f2.id_analisi = a.id) AS last_step,
        a.created_at
    FROM analisi a
    WHERE 1=1
";

if ($search !== "") {
    $search_safe = $mysqli->real_escape_string($search);
    $sql .= " AND (a.id LIKE '%$search_safe%' 
                OR a.user_id LIKE '%$search_safe%'
                OR a.marca_stimata LIKE '%$search_safe%'
                OR a.modello_stimato LIKE '%$search_safe%')";
}

if ($stato !== "") {
    $stato_safe = $mysqli->real_escape_string($stato);
    $sql .= " AND a.stato = '$stato_safe'";
}

$sql .= " ORDER BY a.created_at DESC";

$result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Autentica – Dashboard Analisi</title>
<link rel="icon" type="image/png" href="images/autentica.png">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Titillium Web (ADM style) -->
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
    --adm-dark: #1b1b1b;
}


* {
    font-family: "Titillium Web", system-ui, sans-serif;
}

body {
    background: var(--adm-bg);
    color: var(--adm-text);
    margin: 0;
}

/* Header ADM */
.adm-topbar {
    background: var(--adm-blue);
    color: #fff;
    padding: 0.35rem 0;
    font-size: 0.78rem;
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

/* Breadcrumb */
.adm-breadcrumb {
    background: #fff;
    border-bottom: 1px solid var(--adm-border);
}

.adm-breadcrumb-inner {
    padding: .55rem 0;
    font-size: .78rem;
    color: var(--adm-gray);
}

/* Buttons */


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
.btn-adm-primary {
    background: var(--adm-blue);
    border-color: var(--adm-blue);
    color: #fff;
    font-weight: 600;
}

.btn-adm-primary:hover {
    background: var(--adm-blue-light);
    border-color: var(--adm-blue-light);
}

.btn-adm-outline {
    border-radius: 999px;
    font-size: .75rem;
    padding: .25rem .75rem;
}

/* Table */
.table thead {
    background: var(--adm-blue-soft);
}

.table td, .table th {
    vertical-align: middle;
}

/* Badges */
.badge-lg {
    font-size: 0.9rem;
    padding: .45em .8em;
}

/* Filter box */
.filter-box {
    background: #fff;
    border: 1px solid var(--adm-border);
    padding: 1rem;
    border-radius: 8px;
}
</style>

</head>
<body>

<!-- TOP BAR -->
<div class="adm-topbar">
    <div class="container d-flex justify-content-between">
        <span>Portale Autentica – Area Riservata</span>
        <span>Utente: <strong><?= htmlentities($_SESSION['user_id']) ?></strong></span>
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

        <div class="d-flex" style="gap:.5rem;">
            <a href="autentica.php?reset=1" class="btn btn-sm btn-adm-secondary">⬅️ Torna all'app</a>
        </div>
    </div>
</header>


<div class="container py-4">

<!-- TITLE -->
<h3 class="mb-3" style="color:var(--adm-blue); font-weight:700;">📊 Dashboard Analisi</h3>

<!-- FILTRI -->
<div class="filter-box mb-4">

<form method="GET" class="row g-3">

    <div class="col-md-4">
        <input 
            type="text" 
            name="search" 
            value="<?= htmlentities($search); ?>"
            class="form-control" 
            placeholder="Cerca ID, utente, marca, modello..."
        >
    </div>

    <div class="col-md-3">
        <select name="stato" class="form-control">
            <option value="">Tutti gli stati</option>
            <option value="in_corso"    <?= $stato=="in_corso" ? "selected" : "" ?>>In corso</option>
            <option value="completata"  <?= $stato=="completata" ? "selected" : "" ?>>Completata</option>
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

<?php while ($row = $result->fetch_assoc()): ?>

<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlentities($row['user_id']) ?></td>

    <td>
        <?php if ($row['stato'] == 'completata'): ?>
            <span class="badge bg-success">Completata</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">In corso</span>
        <?php endif; ?>
    </td>

    <td><?= htmlentities($row['marca_stimata']) ?></td>
    <td><?= htmlentities($row['modello_stimato']) ?></td>

    <td class="text-center">
        <span class="badge bg-info text-dark">
            <?= intval($row['totale_foto']); ?> foto
        </span>
    </td>

    <td class="text-center">
        <strong><?= ($row['last_step'] ? $row['last_step'] : 1); ?></strong>
    </td>

    <td class="text-center">
        <?php 
        $p = $row['percentuale_contraffazione'];
        if (is_numeric($p)) {
            if ($p < 33) {
                echo "<span class='badge bg-success badge-lg'>{$p}%</span>";
            } elseif ($p < 70) {
                echo "<span class='badge bg-warning text-dark badge-lg'>{$p}%</span>";
            } else {
                echo "<span class='badge bg-danger badge-lg'>{$p}%</span>";
            }
        } else {
            echo "<span class='badge bg-secondary'>N.D.</span>";
        }
        ?>
    </td>

    <td><?= $row['created_at'] ?></td>

    <td class="text-center">
        <a href="autentica_admin_dettaglio.php?id=<?= $row['id'] ?>" 
           class="btn btn-sm btn-adm-primary">
            Apri
        </a>
    </td>

</tr>

<?php endwhile; ?>

</tbody>
</table>

</div>
</div>

</div>

</body>
</html>
