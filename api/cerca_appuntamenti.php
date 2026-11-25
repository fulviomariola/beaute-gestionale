<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
requireAdminApi();

$q = trim($_GET['q'] ?? '');
$len = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);

if ($len < 2) {
    echo json_encode([]);
    exit;
}

try {
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.data_appuntamento,
            a.ora_inizio,
            a.ora_fine,
            a.stato,
            a.note_trattamento,
            c.nome AS cliente_nome,
            c.cognome AS cliente_cognome,
            c.telefono AS cliente_tel,
            cab.nome AS cabina_nome,
            t.nome AS trattamento_nome,
            t.prezzo AS trattamento_prezzo
        FROM `appuntamenti` a
        JOIN `clienti` c ON a.id_cliente = c.id
        JOIN `cabine` cab ON a.id_cabina = cab.id
        LEFT JOIN `trattamenti` t ON a.id_trattamento = t.id
        WHERE a.stato != 'cancellato'
          AND (
              c.cognome LIKE ?
              OR c.nome LIKE ?
              OR CONCAT(c.cognome, ' ', c.nome) LIKE ?
              OR CONCAT(c.nome, ' ', c.cognome) LIKE ?
              OR c.telefono LIKE ?
          )
        ORDER BY a.data_appuntamento ASC, a.ora_inizio ASC
        LIMIT 20
    ");

    $stmt->execute([$like, $like, $like, $like, $like]);

    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('cerca_appuntamenti: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore nella ricerca appuntamenti']);
}