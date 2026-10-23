-- 1. Creazione del Database
CREATE DATABASE IF NOT EXISTS `labeaute_gestionale`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `labeaute_gestionale`;

-- 2. Tabella Utenti Admin (Accesso sicuro all'area riservata)
CREATE TABLE IF NOT EXISTS `utenti_admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `creato_il` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Tabella Cabine (Configurazione risorse fisiche)
CREATE TABLE IF NOT EXISTS `cabine` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero` INT NOT NULL UNIQUE,
    `nome` VARCHAR(50) NOT NULL,
    `attiva` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- 4. Tabella Trattamenti (Listino di base modificabile)
CREATE TABLE IF NOT EXISTS `trattamenti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL,
    `durata_minuti_default` INT DEFAULT 60,
    `attivo` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- 5. Tabella Clienti (Anagrafica essenziale)
CREATE TABLE IF NOT EXISTS `clienti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(60) NOT NULL,
    `cognome` VARCHAR(60) NOT NULL,
    `telefono` VARCHAR(30) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `creato_il` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nome_cognome` (`cognome`, `nome`),
    INDEX `idx_telefono` (`telefono`)
) ENGINE=InnoDB;

-- 6. Tabella Appuntamenti (Gestione slot, sovrapposizioni e note)
CREATE TABLE IF NOT EXISTS `appuntamenti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_cabina` INT NOT NULL,
    `id_cliente` INT NOT NULL,
    `id_trattamento` INT DEFAULT NULL,
    `data_appuntamento` DATE NOT NULL,
    `ora_inizio` TIME NOT NULL,
    `ora_fine` TIME NOT NULL,
    `note_trattamento` VARCHAR(255) DEFAULT NULL,
    `stato` ENUM('prenotato', 'completato', 'cancellato') DEFAULT 'prenotato',
    `creato_il` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`id_cabina`) REFERENCES `cabine`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`id_cliente`) REFERENCES `clienti`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_trattamento`) REFERENCES `trattamenti`(`id`) ON DELETE SET NULL,
    
    -- Indici per caricamento istantaneo dell'agenda giornaliera
    INDEX `idx_agenda_giorno` (`data_appuntamento`, `id_cabina`, `ora_inizio`)
) ENGINE=InnoDB;

-- ==========================================================================
-- POPOLAMENTO DATI INIZIALI
-- ==========================================================================

-- Inserimento delle 4 Cabine
INSERT INTO `cabine` (`numero`, `nome`) VALUES
(1, 'Cabina 1'),
(2, 'Cabina 2'),
(3, 'Cabina 3'),
(4, 'Cabina 4')
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

-- Inserimento trattamenti fittizi di prova
INSERT INTO `trattamenti` (`nome`, `durata_minuti_default`) VALUES
('Cura Mani e Piedi', 60),
('Trattamento Viso', 60),
('Trattamento Corpo', 60),
('Endosphères Therapy', 60),
('Massaggio', 60),
('Epilazione', 30);
