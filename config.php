<?php
// Avvio della sessione per proteggere l'area riservata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Parametri di connessione al database locale
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'labeaute_gestionale');
define('DB_USER', 'root');
define('DB_PASS', 'fu76ro5');
define('DB_CHARSET', 'utf8mb4');

// Opzioni PDO per sicurezza e gestione errori
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Funzione helper per verificare se l'amministratore è autenticato
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}

// Funzione di protezione pagine con controllo timeout (30 minuti di inattività)
function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: index.php');
        exit;
    }

    $inattivitaMax = 1800; // 30 minuti in secondi
    if (isset($_SESSION['ultimo_accesso']) && (time() - $_SESSION['ultimo_accesso'] > $inattivitaMax)) {
        header('Location: logout.php');
        exit;
    }
    
    $_SESSION['ultimo_accesso'] = time(); // Rinnovo del timestamp ad ogni azione
}