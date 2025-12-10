<?php
session_start();
include "open_db.php"; // deve definire $conn

$error = "";

/***************************************************
 * LOGIN SUBMIT
 ***************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST["user"]) && !empty($_POST["pwd"])) {

    $_SESSION['qualifica'] = "";
    $_SESSION['profili']   = [];

    $myUserID   = strtoupper(trim(str_replace("'", "`", $_POST["user"])));
    $myPassword = $_POST["pwd"];

    /***************************************************
     * Carico l'elenco dei profili (admin, viewer, ...)
     ***************************************************/
    $profili_arr = [];
    $sql = "SELECT qualifica FROM profili";
    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $profili_arr[] = $row["qualifica"];
    }

    /***************************************************
     * Recupero l’utente dalla vista v_anagrafica_personale
     ***************************************************/
    $sql = "
        SELECT *
        FROM v_anagrafica_personale
        WHERE UCASE(userid) = ?
          AND fl_attivo = TRUE
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $myUserID);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {

        $row = $res->fetch_assoc();
        $stored_hash = $row['password'];

        /***************************************************
         * Verifica password (hash BCRYPT / PASSWORD_DEFAULT)
         ***************************************************/
        if (password_verify($myPassword, $stored_hash)) {

            $_SESSION['qualifica'] = $row['qualifica'];
            $_SESSION['nome']      = $row['nome'];
            $_SESSION['cognome']   = strtoupper($row['cognome']);
            $_SESSION['user_id']    = $row['userid'];
            $_SESSION['home']      = 'autentica.php';
            $_SESSION['profili']   = [];

            // Costruzione lista profili attivi
            foreach ($profili_arr as $profile) {
                if (isset($row[$profile]) && $row[$profile] == 1) {
                    $_SESSION['profili'][] = $profile;
                }
            }

            header("Location: " . $_SESSION['home']);
            exit;

        } else {
            $error = "Password errata.";
        }
    } else {
        $error = "Utente non trovato o non attivo.";
    }
}

/***************************************************
 * Cambio profilo senza login (come tuo codice)
 ***************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST["profile"])) {

    $_SESSION['qualifica'] = $_POST["profile"];

    $sql = "SELECT * FROM profili WHERE qualifica='" . $_SESSION['qualifica'] . "'";
    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $_SESSION['home'] = $row['home'];
    }

    header("Location: " . $_SESSION['home']);
    exit;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Autentica — Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

<style>
    body {
        background: linear-gradient(135deg, #f4f4f4, #eaeaea);
        font-family: "Inter", sans-serif;
    }
    .login-card {
        border-radius: 20px;
        padding: 40px;
        background: #fff;
        box-shadow: 0 15px 40px rgba(0,0,0,0.08);
        animation: fadeIn .6s ease;
    }
    .brand-logo {
        font-size: 42px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    .btn-premium {
        background: #000;
        color: #fff;
        font-weight: 600;
        padding: 12px;
        border-radius: 12px;
        border: none;
        transition: .2s;
    }
    .btn-premium:hover {
        background: #333;
        transform: translateY(-2px);
    }
    @keyframes fadeIn {
        from { opacity:0; transform:translateY(20px); }
        to   { opacity:1; transform:translateY(0); }
    }
</style>
</head>

<body class="d-flex align-items-center justify-content-center vh-100">

<div class="container" style="max-width: 420px;">

    <div class="text-center mb-4">
        <div class="brand-logo">AUTENTICA</div>
        <p class="text-muted" style="letter-spacing:1px;">Luxury Authentication Suite</p>
    </div>

    <div class="login-card">
        <h3 class="text-center mb-4">Accedi</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="fw-semibold">User ID</label>
            <input type="text" name="user" class="form-control mb-3 p-3" required>

            <label class="fw-semibold">Password</label>
            <input type="password" name="pwd" class="form-control mb-4 p-3" required>

            <button class="btn btn-premium w-100">Entra</button>
        </form>
    </div>

</div>

</body>
</html>
