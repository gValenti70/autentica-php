<?php
session_start();

function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
$API_BASE = env('API_BASE', 'https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net');

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payload = json_encode([
        "user_id"  => $_POST["user_id"] ?? "",
        "password" => $_POST["password"] ?? ""
    ]);

    $ch = curl_init("$API_BASE/auth/login");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($resp, true);

        $_SESSION["user_id"] = $data["user_id"];
        $_SESSION["role"]    = $data["role"];

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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

* {
    font-family: "Titillium Web", system-ui, sans-serif;
}

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

.login-header h4 {
    margin: 0;
    font-weight: 700;
    color: var(--adm-blue);
}

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

.form-label {
    font-weight: 600;
    font-size: .85rem;
}
</style>
</head>

<body>

<div class="login-card">

    <div class="login-header">
        <div class="login-logo">ADM</div>
        <h4>Autentica</h4>
        <small>Accesso Riservato</small>
    </div>

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

