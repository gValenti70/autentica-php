<?php
session_start();

/* ===========================
   ENV HELPER
=========================== */
function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

$API_BASE = env('API_BASE', 'https://autentica-php-gjevhmb7b2fdf5ct.swedencentral-01.azurewebsites.net');
$error = "";

/* ===========================
   BACKEND REACHABILITY TEST
   ⚠️ RIMUOVERE TUTTO QUESTO BLOCCO SE OK
=========================== 
// [DEBUG]
$backend_debug = null;

if ($API_BASE) {
    // [DEBUG]
    $testUrl = rtrim($API_BASE, '/') . '/health'; // oppure /docs

    // [DEBUG]
    $ch_test = curl_init($testUrl);
    curl_setopt_array($ch_test, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    // [DEBUG]
    $testResp = curl_exec($ch_test);
    $testErr  = curl_error($ch_test);
    $testCode = curl_getinfo($ch_test, CURLINFO_HTTP_CODE);
    curl_close($ch_test);

    // [DEBUG]
    $backend_debug = [
        'url'   => $testUrl,
        'code'  => $testCode,
        'error' => $testErr,
        'body'  => $testResp ? substr($testResp, 0, 200) : null
    ];
}
// [DEBUG] FINE BLOCCO TEST BACKEND
*/
/* ===========================
   LOGIN
=========================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payload = json_encode([
        "user_id"  => $_POST["user_id"] ?? "",
        "password" => $_POST["password"] ?? ""
    ]);

    $ch = curl_init(rtrim($API_BASE, '/') . "/auth/login");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FAILONERROR => false
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        $error = "Errore backend: " . $curlErr;
    } elseif ($code === 200) {
        $data = json_decode($resp, true);

        $_SESSION["user_id"] = $data["user_id"] ?? null;
        $_SESSION["role"]    = $data["role"] ?? null;

        $redirect = $_GET["pag"] ?? "autentica.php";
        header("Location: $redirect");
        exit;
    } else {
        $error = "Credenziali non valide";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Autentica – Accesso Riservato</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="icon" type="image/png" href="images/autentica.png">
<link href="https://fastly.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --adm-blue: #003b70;
    --adm-blue-light: #e6ecf7;
    --adm-bg: #f5f6fa;
    --adm-border: #d0d7e2;
    --adm-text: #243447;
    --adm-gray: #6b7480;
}
* { font-family: "Titillium Web", system-ui, sans-serif; }
body {
    background: linear-gradient(135deg, #eef2f8, #f7f9fc);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--adm-text);
}
.login-card {
    background: #fff;
    width: 100%;
    max-width: 420px;
    border-radius: 12px;
    border: 1px solid var(--adm-border);
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    padding: 28px;
}
.login-header {
    text-align: center;
    margin-bottom: 24px;
}
.login-logo {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--adm-blue), var(--adm-blue-light));
    color: #fff;
    font-weight: 700;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.login-header h4 { margin: 0; font-weight: 700; color: var(--adm-blue); }
.login-header small {
    color: var(--adm-gray);
    text-transform: uppercase;
    letter-spacing: .06em;
    font-size: .7rem;
}
.btn-adm-primary {
    background: var(--adm-blue);
    border-color: var(--adm-blue);
    color: #fff;
    font-weight: 600;
}
.btn-adm-primary:hover {
    background: #002e5c;
    border-color: #002e5c;
}
.form-label { font-weight: 600; font-size: .85rem; }
</style>
</head>

<body>

<div class="login-card">

    <div class="login-header">
        <div class="login-logo">ADM</div>
        <h4>Autentica</h4>
        <small>Accesso Riservato</small>
    </div>

    <!-- ===========================
         DEBUG BACKEND VISIVO
         ⚠️ RIMUOVERE COMPLETAMENTE
    ============================ -->
    <?php if ($backend_debug): ?>
        <div class="alert alert-secondary small">
            <strong>Backend check</strong><br>
            URL: <?= htmlentities($backend_debug['url']) ?><br>
            HTTP: <?= $backend_debug['code'] ?><br>
            <?php if ($backend_debug['error']): ?>
                <span class="text-danger">
                    cURL error: <?= htmlentities($backend_debug['error']) ?>
                </span>
            <?php else: ?>
                <span class="text-success">Backend raggiungibile</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <!-- [DEBUG] FINE BLOCCO -->

    <?php if ($error): ?>
        <div class="alert alert-danger text-center py-2">
            <?= htmlentities($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-3">
            <label class="form-label">Utente</label>
            <input type="text" name="user_id" class="form-control" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-adm-primary w-100 mt-2">
            Accedi
        </button>

    </form>

</div>

</body>
</html>






