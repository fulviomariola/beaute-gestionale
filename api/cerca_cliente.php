<?php
header('Content-Type: application/json');
require_once '../config.php';
requireAdminApi();

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, cognome, telefono 
        FROM clienti 
        WHERE cognome LIKE :q1 OR nome LIKE :q2 OR telefono LIKE :q3
        ORDER BY cognome ASC, nome ASC 
        LIMIT 8
    ");
    $like = '%' . $query . '%';
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('cerca_cliente: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore query']);
}