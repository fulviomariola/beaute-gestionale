<?php
require_once 'config.php';

// Definisci le credenziali iniziali
$username = 'admin';
$password = 'Segreta2026!'; // Modificala a piacere prima di eseguire

// Generazione dell'hash sicuro con algoritmo bcrypt/argon2
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO `utenti_admin` (`username`, `password_hash`) 
        VALUES (:username, :password_hash)
        ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`)
    ");
    
    $stmt->execute([
        ':username'      => $username,
        ':password_hash' => $passwordHash
    ]);

    echo "Utente '{$username}' creato o aggiornato con successo!";
} catch (PDOException $e) {
    echo "Errore durante la creazione dell'amministratore: " . $e->getMessage();
}
