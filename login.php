<?php
require_once 'config.php';

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM utenti_admin WHERE username = :user LIMIT 1");
        $stmt->execute([':user' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $admin['username'];
            header('Location: clienti.php');
            exit;
        } else {
            $errore = 'Credenziali non valide.';
        }
    } else {
        $errore = 'Compila tutti i campi.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso Riservato - La Beauté</title>
    <link rel="stylesheet" href="css/admin-theme.css">
    <style>
        .login-card {
            max-width: 360px;
            margin: 80px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .login-card h2 { font-size: 18px; color: #4a4063; margin-bottom: 20px; text-align: center; }
        .login-card input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid var(--border-color); border-radius: 4px; }
        .error-msg { color: #b00020; font-size: 13px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Area Riservata Clienti</h2>
        <?php if ($errore): ?>
            <div class="error-msg"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Nome Utente" required autocomplete="off">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" class="btn-primary" style="width: 100%;">Accedi</button>
        </form>
        <div style="text-align: center; margin-top: 15px;">
            <a href="agenda.php" style="color: #726289; font-size: 13px; text-decoration: none;">← Torna all'Agenda</a>
        </div>
    </div>
</body>
</html>
