<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

$idApp          = filter_input(INPUT_POST, 'id_appuntamento', FILTER_VALIDATE_INT) ?: null;
$idCabina       = filter_input(INPUT_POST, 'id_cabina', FILTER_VALIDATE_INT);
$dataApp        = trim($_POST['data_appuntamento'] ?? '');
$oraInizio      = trim($_POST['ora_inizio'] ?? '');
$oraFine        = trim($_POST['ora_fine'] ?? '');
$idTrattamento  = filter_input(INPUT_POST, 'id_trattamento', FILTER_VALIDATE_INT) ?: null;
$noteTratt      = trim($_POST['note_trattamento'] ?? '');
$idCliente      = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
$nomeCliente    = trim($_POST['nuovo_nome'] ?? '');
$cognomeCliente = trim($_POST['nuovo_cognome'] ?? '');
$telCliente     = trim($_POST['nuovo_telefono'] ?? '');

if (!$idCabina || empty($dataApp) || empty($oraInizio) || empty($oraFine)) {
    echo json_encode(['success' => false, 'message' => 'Dati incompleti.']);
    exit;
}

// Normalizza orari
if (strlen($oraInizio) === 5) $oraInizio .= ':00';
if (strlen($oraFine) === 5)   $oraFine   .= ':00';

if ($oraInizio >= $oraFine) {
    echo json_encode(['success' => false, 'message' => "L'orario di fine deve essere successivo all'inizio."]);
    exit;
}

try {
    // CONTROLLO SOVRAPPOSIZIONI (Permette contigui: 9:15 > 9:15 è FALSO, quindi non blocca)
    $sqlCheck = "SELECT COUNT(*) FROM appuntamenti WHERE id_cabina = :cabina AND data_appuntamento = :data AND stato != 'cancellato' AND (ora_inizio < :fine AND ora_fine > :inizio)";
    $paramsCheck = [':cabina' => $idCabina, ':data' => $dataApp, ':inizio' => $oraInizio, ':fine' => $oraFine];
    
    if ($idApp) {
        $sqlCheck .= " AND id != :id_app";
        $paramsCheck[':id_app'] = $idApp;
    }
    
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($paramsCheck);
    
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cella occupata: c\'è già un appuntamento in questa fascia.']);
        exit;
    }

    // Gestione Cliente
    if (empty($idCliente)) {
        if (empty($nomeCliente) && empty($cognomeCliente)) {
            echo json_encode(['success' => false, 'message' => 'Cliente non valido.']);
            exit;
        }
        $stmtCheckCli = $pdo->prepare("SELECT id FROM clienti WHERE (nome = :nome AND cognome = :cognome) OR telefono = :tel LIMIT 1");
        $stmtCheckCli->execute([':nome' => $nomeCliente, ':cognome' => $cognomeCliente, ':tel' => $telCliente]);
        $clienteEsistente = $stmtCheckCli->fetch();
        
        if ($clienteEsistente) {
            $idCliente = (int)$clienteEsistente['id'];
        } else {
            $stmtCli = $pdo->prepare("INSERT INTO clienti (nome, cognome, telefono) VALUES (:nome, :cognome, :tel)");
            $stmtCli->execute([':nome' => $nomeCliente, ':cognome' => $cognomeCliente, ':tel' => $telCliente]);
            $idCliente = (int)$pdo->lastInsertId();
        }
    }

    // Salvataggio
    if ($idApp) {
        $stmtSave = $pdo->prepare("UPDATE appuntamenti SET id_cabina=:cabina, id_cliente=:cliente, id_trattamento=:tratt, data_appuntamento=:data, ora_inizio=:inizio, ora_fine=:fine, note_trattamento=:note WHERE id=:id");
        $stmtSave->execute([':cabina'=>$idCabina, ':cliente'=>$idCliente, ':tratt'=>$idTrattamento, ':data'=>$dataApp, ':inizio'=>$oraInizio, ':fine'=>$oraFine, ':note'=>$noteTratt, ':id'=>$idApp]);
    } else {
        $stmtSave = $pdo->prepare("INSERT INTO appuntamenti (id_cabina, id_cliente, id_trattamento, data_appuntamento, ora_inizio, ora_fine, note_trattamento) VALUES (:cabina, :cliente, :tratt, :data, :inizio, :fine, :note)");
        $stmtSave->execute([':cabina'=>$idCabina, ':cliente'=>$idCliente, ':tratt'=>$idTrattamento, ':data'=>$dataApp, ':inizio'=>$oraInizio, ':fine'=>$oraFine, ':note'=>$noteTratt]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Errore DB: ' . $e->getMessage()]);
}