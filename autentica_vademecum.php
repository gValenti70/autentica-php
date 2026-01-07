<?php
session_start();

/**
 * ======================================================
 *  AUTENTICA - ADMIN VADEMECUM (CRUD)
 *  Scelta A: SOLO raw_text + (brand, brand_norm, model, model_norm)
 *  Backend:
 *   - GET    /admin/vademecum?brand=&model=&q=&skip=&limit=
 *   - GET    /admin/vademecum/{id}
 *   - POST   /admin/vademecum
 *   - PUT    /admin/vademecum/{id}
 *   - DELETE /admin/vademecum/{id}
 * ======================================================
 */

if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=" . urlencode("autentica_vademecum.php"));
    exit;
}

$API_BASE = 'https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net';

// $API_BASE = "https://xxxx.azurewebsites.net";

function safe_str($v) {
    return is_string($v) ? trim($v) : "";
}

function curl_json_get($url, &$http_code = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $resp = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $resp === null || $resp === "") return null;
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

function curl_json_post($url, array $payload, &$http_code = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $resp === null || $resp === "") return null;
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

/**
 * ✅ MINIMA aggiunta: supporto PUT/DELETE senza stravolgere nulla
 */
function curl_json_request($method, $url, array $payload = null, &$http_code = null) {
    $ch = curl_init($url);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $resp === null || $resp === "") return null;
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

function norm_simple($s) {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[\s\-_]+/','', $s);
    return $s;
}

/**
 * ======================================================
 * ACTION HANDLER
 * ======================================================
 */
$flash_ok = null;
$flash_err = null;

$action = safe_str($_POST['action'] ?? "");

// create
if ($action === "create") {
    $brand = safe_str($_POST['brand'] ?? "");
    $model = safe_str($_POST['model'] ?? "");
    $raw_text = safe_str($_POST['raw_text'] ?? "");
    // updated_at lasciato nel form ma NON lo mandiamo: backend lo setta lui

    if ($brand === "" || $model === "" || $raw_text === "") {
        $flash_err = "Compila brand, model e raw_text.";
    } else {
        $payload = [
            "type" => "model",
            "brand" => $brand,
            "model" => $model,
            "raw_text" => $raw_text
        ];

        $code = null;
        $res = curl_json_post($API_BASE . "/admin/vademecum", $payload, $code);

        // backend ritorna direttamente l'oggetto creato (con id), non {status:"ok"}
        if (is_array($res) && isset($res["id"])) {
            $flash_ok = "Vademecum creato.";
        } else {
            $flash_err = "Errore creazione (HTTP $code). " . htmlentities(json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }
}

// update
if ($action === "update") {
    $id = safe_str($_POST['id'] ?? "");
    $brand = safe_str($_POST['brand'] ?? "");
    $model = safe_str($_POST['model'] ?? "");
    $raw_text = safe_str($_POST['raw_text'] ?? "");

    if ($id === "" || $brand === "" || $model === "" || $raw_text === "") {
        $flash_err = "Compila id, brand, model e raw_text.";
    } else {
        $payload = [
            "brand" => $brand,
            "model" => $model,
            "raw_text" => $raw_text
        ];

        $code = null;
        $res = curl_json_request("PUT", $API_BASE . "/admin/vademecum/" . urlencode($id), $payload, $code);

        if (is_array($res) && isset($res["id"])) {
            $flash_ok = "Vademecum aggiornato.";
        } else {
            $flash_err = "Errore update (HTTP $code). " . htmlentities(json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }
}

// delete
if ($action === "delete") {
    $id = safe_str($_POST['id'] ?? "");
    if ($id === "") {
        $flash_err = "ID mancante per delete.";
    } else {
        $code = null;
        $res = curl_json_request("DELETE", $API_BASE . "/admin/vademecum/" . urlencode($id), null, $code);

        if (is_array($res) && ($res["status"] ?? "") === "ok") {
            $flash_ok = "Vademecum eliminato.";
        } else {
            $flash_err = "Errore delete (HTTP $code). " . htmlentities(json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }
}

/**
 * ======================================================
 * VIEW STATE
 * ======================================================
 */
$mode = safe_str($_GET['mode'] ?? "list"); // list | edit | new
$edit_id = safe_str($_GET['id'] ?? "");

// filtri ricerca
$q_brand = safe_str($_GET['brand'] ?? "");
$q_model = safe_str($_GET['model'] ?? "");
$q_q     = safe_str($_GET['q'] ?? "");

// paginazione (mantengo pagina ma la traduco in skip)
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$skip  = ($page - 1) * $limit;

$qs = http_build_query([
    "brand" => $q_brand,
    "model" => $q_model,
    "q" => $q_q,
    "skip" => $skip,
    "limit" => $limit
]);

$list = [];
$total = null;

if ($mode === "list") {
    $code = null;
    $res = curl_json_get($API_BASE . "/admin/vademecum?" . $qs, $code);
    if (is_array($res)) {
        // shape backend: {count:n, items:[...]}
        if (isset($res["items"]) && is_array($res["items"])) {
            $list = $res["items"];
            $total = $res["count"] ?? null;
        } else {
            // fallback (nel dubbio)
            $list = $res;
            $total = null;
        }
    } else {
        $flash_err = $flash_err ?: "Impossibile caricare la lista (HTTP $code).";
    }
}

$edit_doc = null;
if (($mode === "edit") && $edit_id) {
    $code = null;
    $res = curl_json_get($API_BASE . "/admin/vademecum/" . urlencode($edit_id), $code);
    if (is_array($res) && isset($res["id"])) {
        $edit_doc = $res;
    } else {
        $flash_err = "Impossibile caricare il dettaglio (HTTP $code).";
        $mode = "list";
    }
}

// helper per date input
function to_date_ymd($iso) {
    if (!is_string($iso) || $iso === "") return "";
    return substr($iso, 0, 10);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Autentica – Admin Vademecum</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="images/autentica.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
  --adm-blue:#003b80;
  --adm-bg:#f4f6f9;
  --adm-border:#d0d7e2;
  --adm-soft:#e6ecf7;
}
body{ background:var(--adm-bg); }
.header-adm{ background:var(--adm-blue); color:#fff; padding:18px 24px; border-radius:8px; margin-bottom:18px; }
.btn-adm-primary{ background:var(--adm-blue); border-color:var(--adm-blue); color:#fff; font-weight:600; }
.btn-adm-secondary{ background:var(--adm-soft); border-color:var(--adm-blue); color:var(--adm-blue); font-weight:600; }
.card-header{ background:var(--adm-soft); font-weight:600; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
.smallmuted{ color:#6c757d; font-size:.85rem; }
textarea{ min-height: 260px; }
.table td, .table th{ vertical-align: middle; }
.badge-norm{ background:#eef2f7; color:#344054; border:1px solid #d0d7e2; }
</style>
</head>

<body>
<div class="container py-4">

  <div class="header-adm d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h2 class="m-0">Admin Vademecum</h2>
      <div class="smallmuted text-white-50">Gestione vademecum (raw_text) – utente: <strong><?= htmlentities($_SESSION['user_id'] ?? '') ?></strong></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-adm-secondary" href="autentica_admin.php">⬅ Dashboard</a>
      <a class="btn btn-adm-secondary" href="autentica.php?reset=1">⬅ App</a>
    </div>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= htmlentities($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger"><?= $flash_err ?></div>
  <?php endif; ?>

  <!-- NAV -->
  <div class="d-flex gap-2 mb-3 flex-wrap">
    <a class="btn btn-adm-secondary" href="?mode=list">📚 Lista</a>
    <a class="btn btn-adm-primary" href="?mode=new">➕ Nuovo vademecum</a>
  </div>

  <?php if ($mode === "new"): ?>
    <!-- CREATE FORM -->
    <div class="card shadow-sm mb-4">
      <div class="card-header">➕ Crea nuovo vademecum</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="create">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Brand</label>
              <input class="form-control" name="brand" placeholder="Es. Chanel" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Model</label>
              <input class="form-control" name="model" placeholder="Es. Classic Flap" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Updated at (opzionale)</label>
              <input class="form-control" type="date" name="updated_at">
              <div class="smallmuted mt-1">Salvato automaticamente dal backend.</div>
            </div>

            <div class="col-12">
              <label class="form-label">raw_text</label>
              <textarea class="form-control mono" name="raw_text" required placeholder="Incolla qui il vademecum in testo libero..."></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-adm-primary">Salva</button>
              <a class="btn btn-outline-secondary" href="?mode=list">Annulla</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($mode === "edit" && $edit_doc): ?>
    <!-- EDIT FORM -->
    <div class="card shadow-sm mb-4">
      <div class="card-header">✏️ Modifica vademecum</div>
      <div class="card-body">
        <form method="POST" class="mb-3">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= htmlentities($edit_doc["id"]) ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Brand</label>
              <input class="form-control" name="brand" required value="<?= htmlentities($edit_doc["brand"] ?? "") ?>">
              <div class="smallmuted mt-1">brand_norm: <span class="badge badge-norm"><?= htmlentities($edit_doc["brand_norm"] ?? "") ?></span></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Model</label>
              <input class="form-control" name="model" required value="<?= htmlentities($edit_doc["model"] ?? "") ?>">
              <div class="smallmuted mt-1">model_norm: <span class="badge badge-norm"><?= htmlentities($edit_doc["model_norm"] ?? "") ?></span></div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Updated at</label>
              <input class="form-control" type="date" name="updated_at" value="<?= htmlentities(to_date_ymd($edit_doc["updated_at"] ?? "")) ?>" disabled>
              <div class="smallmuted mt-1">Gestito dal backend (auto).</div>
            </div>

            <div class="col-12">
              <label class="form-label">raw_text</label>
              <textarea class="form-control mono" name="raw_text" required><?= htmlentities($edit_doc["raw_text"] ?? "") ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-adm-primary">Salva modifiche</button>
              <a class="btn btn-outline-secondary" href="?mode=list">Torna alla lista</a>
            </div>
          </div>
        </form>

        <hr>

        <form method="POST" onsubmit="return confirm('Confermi eliminazione vademecum?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= htmlentities($edit_doc["id"]) ?>">
          <button class="btn btn-danger">🗑 Elimina</button>
        </form>

        <div class="smallmuted mt-3">
          ID: <span class="mono"><?= htmlentities($edit_doc["id"]) ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($mode === "list"): ?>
    <!-- SEARCH + LIST -->
    <div class="card shadow-sm mb-3">
      <div class="card-header">🔎 Ricerca</div>
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="mode" value="list">

          <div class="col-md-3">
            <label class="form-label">Brand</label>
            <input class="form-control" name="brand" value="<?= htmlentities($q_brand) ?>" placeholder="Es. Chanel">
          </div>
          <div class="col-md-3">
            <label class="form-label">Model</label>
            <input class="form-control" name="model" value="<?= htmlentities($q_model) ?>" placeholder="Es. Classic Flap">
          </div>
          <div class="col-md-4">
            <label class="form-label">Testo libero</label>
            <input class="form-control" name="q" value="<?= htmlentities($q_q) ?>" placeholder="Cerca dentro raw_text...">
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-adm-primary w-100">Cerca</button>
            <a class="btn btn-outline-secondary w-100" href="?mode=list">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>📚 Vademecum</div>
        <div class="smallmuted">
          <?= $total !== null ? "Totale: <strong>" . (int)$total . "</strong>" : "Risultati: <strong>" . count($list) . "</strong>" ?>
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:22%">Brand</th>
                <th style="width:22%">Model</th>
                <th style="width:16%">Norm</th>
                <th style="width:18%">Updated</th>
                <th style="width:12%">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$list || count($list) === 0): ?>
                <tr><td colspan="5" class="text-center p-4 text-muted">Nessun vademecum trovato.</td></tr>
              <?php else: ?>
                <?php foreach ($list as $r): ?>
                  <?php
                    $id = $r["id"] ?? ($r["_id"] ?? "");
                    $brand = $r["brand"] ?? "";
                    $model = $r["model"] ?? "";
                    $bn = $r["brand_norm"] ?? "";
                    $mn = $r["model_norm"] ?? "";
                    $ua = $r["updated_at"] ?? "";
                  ?>
                  <tr>
                    <td><strong><?= htmlentities($brand) ?></strong></td>
                    <td><?= htmlentities($model) ?></td>
                    <td class="smallmuted">
                      <span class="badge badge-norm"><?= htmlentities($bn) ?></span>
                      <span class="badge badge-norm"><?= htmlentities($mn) ?></span>
                    </td>
                    <td class="smallmuted"><?= htmlentities($ua ?: "—") ?></td>
                    <td>
                      <?php if ($id): ?>
                        <a class="btn btn-sm btn-adm-secondary" href="?mode=edit&id=<?= urlencode($id) ?>">✏️ Modifica</a>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- PAGINATION (semplice) -->
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="smallmuted">Pagina <?= (int)$page ?></div>
        <div class="d-flex gap-2">
          <?php
            $prev = max(1, $page - 1);
            $next = $page + 1;
            $baseQuery = [
              "mode" => "list",
              "brand" => $q_brand,
              "model" => $q_model,
              "q" => $q_q
            ];
          ?>
          <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? "disabled" : "" ?>"
             href="?<?= http_build_query(array_merge($baseQuery, ["page" => $prev])) ?>">⬅ Prev</a>
          <a class="btn btn-sm btn-outline-secondary"
             href="?<?= http_build_query(array_merge($baseQuery, ["page" => $next])) ?>">Next ➡</a>
        </div>
      </div>

    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





