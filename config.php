<?php
// Avvio della sessione per proteggere l'area riservata
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // il cookie non è leggibile da JavaScript
        'cookie_samesite' => 'Lax',  // il browser non lo invia nelle richieste da altri siti
    ]);
}

// Parametri di connessione (file separato, non versionato)
require_once __DIR__ . '/config.local.php';

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
    error_log('Connessione DB: ' . $e->getMessage());
    die("Servizio temporaneamente non disponibile.");
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

// Protezione per le API: risponde in JSON con errore 401 invece di fare redirect
function requireAdminApi(): void {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessione scaduta. Ricarica la pagina e accedi di nuovo.']);
        exit;
    }

    $inattivitaMax = 1800; // 30 minuti
    if (isset($_SESSION['ultimo_accesso']) && (time() - $_SESSION['ultimo_accesso'] > $inattivitaMax)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessione scaduta per inattività. Ricarica la pagina e accedi di nuovo.']);
        exit;
    }

    $_SESSION['ultimo_accesso'] = time();
}

// Token CSRF: uno per sessione
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificaCsrf(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Versione per le API: risponde in JSON con errore 403
function requireCsrfApi(): void {
    if (!verificaCsrf()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Richiesta non valida. Ricarica la pagina e riprova.']);
        exit;
    }
}