<?php
session_start();

// Svuota completamente l'array di sessione
$_SESSION = [];

// Invalida e rimuove il cookie di sessione dal browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Distrugge i dati di sessione salvati sul server
session_destroy();

// Reindirizza alla pagina di login
header('Location: index.php');
exit;