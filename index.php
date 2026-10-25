<?php
require_once 'config.php';

// Se l'utente ha già una sessione attiva valida, rimanda direttamente all'agenda
if (isAdminLoggedIn()) {
    header('Location: agenda.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {$errore = 'Inserisci sia nome utente che password.';
    } else {
        try {
            $stmt =$pdo->prepare("SELECT * FROM `utenti_admin` WHERE `username` = :user LIMIT 1");
            $stmt->execute([':user' =>$username]);
            $utente =$stmt->fetch();

            if ($utente && password_verify($password,$utente['password_hash'])) {
                // Credenziali corrette: nuovo ID di sessione (protegge dal furto di sessione) e parametri
                session_regenerate_id(true);
                $_SESSION['admin_logged']   = true;
                $_SESSION['admin_id']       = $utente['id'];$_SESSION['admin_user']     = $utente['username'];$_SESSION['ultimo_accesso'] = time(); // Avvio timer di inattività

                header('Location: agenda.php');
                exit;
            } else {
                $errore = 'Credenziali non valide. Riprova.';
            }
        } catch (PDOException $e) {$errore = 'Errore di connessione al database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso Gestionale — La Beauté</title>
    <link rel="stylesheet" href="css/admin-theme.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: var(--bg-body, #f7f5f9);
        }

        .login-card {
            background: #fff;
            width: 100%;
            max-width: 380px;
            padding: 35px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(114, 98, 137, 0.12);
            border: 1px solid var(--border-color, #e0d9e8);
            box-sizing: border-box;
        }

        .login-card h1 {
            font-size: 24px;
            color: #4a4063;
            margin: 0 0 8px 0;
            text-align: center;
        }

        .login-card p.subtitle {
            font-size: 13px;
            color: #726289;
            margin: 0 0 25px 0;
            text-align: center;
        }

        .alert-error {
            background-color: #fde8e8;
            color: #c81e1e;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #f8b4b4;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #4a4063;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color, #e0d9e8);
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: #726289;
        }

        .btn-submit {
            width: 100%;
            background-color: #726289;
            color: #fff;
            border: none;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: #5c4e70;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h1>La Beauté</h1>
        <p class="subtitle">Accesso al Gestionale Interno</p>

        <?php if (!empty($errore)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($errore) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="form-group">
                <label for="username">Nome Utente</label>
                <input type="text" id="username" name="username" required autocomplete="username" autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit">Accedi all'Agenda</button>
        </form>
    </div>

</body>
</html>