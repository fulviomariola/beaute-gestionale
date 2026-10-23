<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

$idApp = filter_input(INPUT_POST, 'id_appuntamento', FILTER_VALIDATE_INT);

if (!$idApp) {
    echo json_encode(['success' => false, 'message' => 'ID appuntamento non valido']);
    exit;
}

try {
    // Impostiamo lo stato su cancellato senza perdere lo storico nel DB
    $stmt = $pdo->prepare("UPDATE appuntamenti SET stato = 'cancellato' WHERE id = :id");
    $stmt->execute([':id' => $idApp]);

    echo json_encode(['success' => true, 'message' => 'Appuntamento annullato con successo']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Errore database: ' . $e->getMessage()]);
}