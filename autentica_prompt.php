<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=autentica_prompt.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? "default";

$mysqli = new mysqli("localhost", "root", "", "autentica");
if ($mysqli->connect_errno) {
    die("Errore DB: " . $mysqli->connect_error);
}

$user_id = $mysqli->real_escape_string($user_id);

function clean($v) {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['action'] === 'new_version') {

        $prompt_name = $mysqli->real_escape_string($_POST['prompt_name']);
        $content     = $_POST['content'];
        $comment     = clean($_POST['comment'] ?? '');
        $feedback    = clean($_POST['feedback'] ?? '');

        $res = $mysqli->query("
            SELECT MAX(version) AS v
            FROM prompt_versions
            WHERE prompt_name = '$prompt_name'
              AND user_id = '$user_id'
        ");
        $row = $res->fetch_assoc();
        $new_version = intval($row['v']) + 1;

        $mysqli->query("
            UPDATE prompt_versions
            SET is_active = 0
            WHERE prompt_name='$prompt_name'
              AND user_id='$user_id'
        ");

        $stmt = $mysqli->prepare("
            INSERT INTO prompt_versions
            (user_id, prompt_name, version, content, comment, feedback, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("ssisss", $user_id, $prompt_name, $new_version, $content, $comment, $feedback);
        $stmt->execute();
        $stmt->close();

        $message = "Nuova versione v$new_version creata e attiva.";
    }

    if ($_POST['action'] === 'activate_version') {

        $prompt_name = $mysqli->real_escape_string($_POST['prompt_name']);
        $version     = intval($_POST['version']);

        $mysqli->query("
            UPDATE prompt_versions
            SET is_active = 0
            WHERE prompt_name='$prompt_name'
              AND user_id='$user_id'
        ");

        $mysqli->query("
            UPDATE prompt_versions
            SET is_active = 1
            WHERE prompt_name='$prompt_name'
              AND user_id='$user_id'
              AND version=$version
        ");

        $message = "Versione v$version attivata.";
    }

    if ($_POST['action'] === 'update_feedback') {

        $id        = intval($_POST['id']);
        $feedback  = clean($_POST['feedback']);
        $prompt_name = clean($_POST['prompt_name']);

        $stmt = $mysqli->prepare("
            UPDATE prompt_versions
            SET feedback=?
            WHERE id=? AND user_id=?
        ");
        $stmt->bind_param("sis", $feedback, $id, $user_id);
        $stmt->execute();
        $stmt->close();

        $message = "Feedback aggiornato.";
    }

    if ($_POST['action'] === 'delete_version') {

        $id = intval($_POST['id']);
        $prompt_name = $mysqli->real_escape_string($_POST['prompt_name']);

        $check = $mysqli->query("
            SELECT is_active 
            FROM prompt_versions
            WHERE id = $id AND user_id = '$user_id'
        ");
        $row = $check->fetch_assoc();

        if (!$row) {
            $message = "Errore: versione non trovata.";
        } elseif ($row['is_active'] == 1) {
            $message = "❌ Impossibile eliminare la versione attiva.";
        } else {
            $mysqli->query("DELETE FROM prompt_versions WHERE id = $id");
            $message = "🗑️ Versione eliminata con successo.";
        }
    }
}

$prompts = $mysqli->query("SELECT name FROM prompts ORDER BY name ASC");

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Prompt Manager</title>
    <link rel="icon" type="image/png" href="images/autentica.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --adm-blue: #003b80;
            --adm-blue-light: #e8f0fb;
            --adm-grey: #f2f4f7;
        }

        body {
            background: var(--adm-grey);
        }

        .adm-card {
            border-left: 4px solid var(--adm-blue);
        }

        .adm-header {
            background: var(--adm-blue);
            color: white;
            padding: 14px 22px;
            border-radius: 6px;
        }

        .btn-adm-primary {
            background: var(--adm-blue);
            border-color: var(--adm-blue);
            color: #fff;
            font-weight: 600;
        }
        .btn-adm-primary:hover {
            background: #002e63;
            border-color: #002e63;
            color: white;
        }

        .list-group-item.active {
            background: var(--adm-blue);
            border-color: var(--adm-blue);
        }

        .prompt-preview {
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            background: #fff;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .editor-area {
            height: 300px;
        }

        summary {
            cursor: pointer;
            font-weight: bold;
            color: var(--adm-blue);
        }
    </style>
</head>

<body class="p-4">
<div class="container">

    <div class="adm-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="m-0">⚙️ Prompt Manager</h2>
            <small>User: <?= htmlentities($user_id) ?></small>
        </div>
        <a href="autentica.php?reset=1" class="btn btn-light btn-sm fw-bold">⬅ Torna all'app</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm"><?= $message ?></div>
    <?php endif; ?>

    <div class="row">

        <!-- LISTA PROMPT -->
        <div class="col-md-3">
            <div class="list-group shadow-sm">
                <div class="list-group-item active fw-bold">Prompt disponibili</div>

                <?php while ($p = $prompts->fetch_assoc()): ?>
                <a href="?prompt=<?= urlencode($p['name']) ?>"
                   class="list-group-item list-group-item-action
                   <?= (isset($_GET['prompt']) && $_GET['prompt'] === $p['name']) ? 'active' : '' ?>">
                    <?= $p['name'] ?>
                </a>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- AREA DETTAGLIO -->
        <div class="col-md-9">

<?php if (isset($_GET['prompt'])): 

    $prompt_name = $mysqli->real_escape_string($_GET['prompt']);

    $q = $mysqli->query("
        SELECT version
        FROM prompt_versions
        WHERE user_id='$user_id'
          AND prompt_name='$prompt_name'
          AND is_active=1
        LIMIT 1
    ");
    $row = $q->fetch_assoc();
    $active_version = $row['version'] ?? null;

    $versions = $mysqli->query("
        SELECT *
        FROM prompt_versions
        WHERE user_id = '$user_id'
          AND prompt_name = '$prompt_name'
        ORDER BY version DESC
    ");
?>

            <div class="card adm-card shadow-sm">

                <div class="card-header bg-white">
                    <h4 class="m-0 text-primary">✏️ Prompt: <strong><?= $prompt_name ?></strong></h4>
                </div>

                <div class="card-body">

                    <h5 class="text-primary mb-3">📚 Versioni disponibili</h5>

<?php while ($v = $versions->fetch_assoc()): ?>
                    <div class="border rounded p-3 mb-3 bg-light">

                        <div class="d-flex justify-content-between">
                            <strong>Versione v<?= $v['version'] ?></strong>
                            <span class="text-muted"><?= $v['created_at'] ?></span>
                        </div>

                        <?php if ($v['comment']): ?>
                            <p class="text-muted mt-2"><?= nl2br(clean($v['comment'])) ?></p>
                        <?php endif; ?>

                        <?php if ($v['feedback']): ?>
                            <p><strong>Feedback:</strong> <?= nl2br(clean($v['feedback'])) ?></p>
                        <?php endif; ?>

                        <details class="mt-2">
                            <summary>Mostra Prompt</summary>
                            <pre class="prompt-preview mt-2"><?= htmlentities($v['content']) ?></pre>
                        </details>

                        <?php if ($v['version'] != $active_version): ?>
                        <form method="POST" class="mt-2 d-flex gap-2">
                            <input type="hidden" name="action" value="activate_version">
                            <input type="hidden" name="prompt_name" value="<?= $prompt_name ?>">
                            <input type="hidden" name="version" value="<?= $v['version'] ?>">
                            <button class="btn btn-sm btn-adm-primary">Attiva versione</button>
                        </form>

                        <form method="POST" class="mt-2">
                            <input type="hidden" name="action" value="delete_version">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="prompt_name" value="<?= $prompt_name ?>">
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Eliminare questa versione?');">
                                🗑️ Elimina versione
                            </button>
                        </form>

                        <?php else: ?>
                            <span class="badge bg-success mt-2">Attiva</span>
                        <?php endif; ?>

                        <form method="POST" class="mt-3">
                            <input type="hidden" name="action" value="update_feedback">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="prompt_name" value="<?= $prompt_name ?>">

                            <label class="form-label">Modifica Feedback</label>
                            <textarea name="feedback" class="form-control" rows="2"><?= clean($v['feedback']) ?></textarea>

                            <button class="btn btn-adm-primary btn-sm mt-2">Salva feedback</button>
                        </form>

                    </div>
<?php endwhile; ?>

                    <hr>

                    <h5 class="text-primary">➕ Crea nuova versione</h5>

                    <form method="POST">
                        <input type="hidden" name="action" value="new_version">
                        <input type="hidden" name="prompt_name" value="<?= $prompt_name ?>">

                        <label class="form-label">Commento (facoltativo)</label>
                        <input type="text" name="comment" class="form-control mb-3">

                        <label class="form-label">Feedback iniziale</label>
                        <input type="text" name="feedback" class="form-control mb-3">

                        <label class="form-label">Contenuto Prompt</label>
                        <textarea name="content" class="form-control editor-area mb-3" required></textarea>

                        <button class="btn btn-adm-primary">Salva nuova versione</button>
                    </form>

                </div>
            </div>

<?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
