<?php
header('Content-Type: application/json');
require_once '../config.php';

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, cognome, telefono 
        FROM clienti 
        WHERE cognome LIKE :q OR nome LIKE :q OR telefono LIKE :q
        ORDER BY cognome ASC, nome ASC 
        LIMIT 8
    ");
    $stmt->execute([':q' => "%{$query}%"]);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore query']);
}
