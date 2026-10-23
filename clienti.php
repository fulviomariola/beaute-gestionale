<?php
require_once 'config.php';
requireAdminLogin(); // Protezione sessione e verifica timeout inattivita

$msgSuccess = '';$msgError = '';

// Gestione Azioni CRUD Clienti
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione =$_POST['azione'] ?? '';

    if ($azione === 'salva') {$id = !empty($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : null;
        $nome = trim($_POST['nome'] ?? '');
        $cognome = trim($_POST['cognome'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if (empty($nome) || empty($cognome)) {$msgError = 'Nome e Cognome sono obbligatori.';
        } else {
            try {
                if ($id) {
                    $stmt =$pdo->prepare("UPDATE `clienti` SET `nome` = :n, `cognome` = :c, `telefono` = :t, `note` = :not WHERE `id` = :id");
                    $stmt->execute([':n' =>$nome, ':c' => $cognome, ':t' =>$telefono, ':not' => $note, ':id' => $id]);$msgSuccess = 'Cliente aggiornato con successo.';
                } else {
                    $stmt =$pdo->prepare("INSERT INTO `clienti` (`nome`, `cognome`, `telefono`, `note`) VALUES (:n, :c, :t, :not)");
                    $stmt->execute([':n' =>$nome, ':c' => $cognome, ':t' =>$telefono, ':not' => $note]);
                    $msgSuccess = 'Nuovo cliente inserito con successo.';
                }
            } catch (PDOException $e) {$msgError = 'Errore durante il salvataggio dei dati.';
            }
        }
    }

    if ($azione === 'elimina') {
        $id = (int)($_POST['id_cliente'] ?? 0);
        if ($id > 0) {
            try {
                // Rimuove gli appuntamenti collegati per non violare i vincoli relazionali
                $pdo->prepare("DELETE FROM `appuntamenti` WHERE `id_cliente` = :id")->execute([':id' => $id]);$pdo->prepare("DELETE FROM `clienti` WHERE `id` = :id")->execute([':id' => $id]);$msgSuccess = 'Cliente e relativo storico eliminati con successo.';
            } catch (PDOException $e) {$msgError = 'Errore durante l\'eliminazione del cliente.';
            }
        }
    }
}

// Estrazione aggiornata di tutti i clienti con conteggio visite
$query = "
    SELECT 
        c.*,
        COUNT(a.id) AS totale_visite
    FROM clienti c
    LEFT JOIN appuntamenti a ON c.id = a.id_cliente
    GROUP BY c.id
    ORDER BY c.cognome ASC, c.nome ASC
";
$clienti = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anagrafica Clienti - La Beauté</title>
    <link rel="stylesheet" href="css/admin-theme.css">
    <style>
        .client-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .client-table th, .client-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .client-table th {
            background: #f7f4fa;
            color: #4a4063;
            font-weight: 600;
        }
        .search-bar {
            width: 100%;
            max-width: 320px;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
        }
        .btn-action-edit {
            background: #f0ecf4;
            border: 1px solid var(--border-color);
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: #4a4063;
        }
        .btn-action-del {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <header class="top-bar">
        <div>
            <h1>Anagrafica Clienti (Totale: <span id="totaleContatore"><?= count($clienti) ?></span>)</h1>
        </div>
        <div class="actions" style="display: flex; gap: 10px; align-items: center;">
            <a href="agenda.php">📅 Torna all'Agenda</a>
            <a href="logout.php" style="background: #a499a8;">Esci 🚪</a>
        </div>
    </header>

    <?php if (!empty($msgSuccess)): ?>
        <div style="background: #e8f5e9; color: #2e7d32; padding: 10px 14px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #c8e6c9;">
            <?= htmlspecialchars($msgSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($msgError)): ?>
        <div style="background: #fde8e8; color: #c81e1e; padding: 10px 14px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f8b4b4;">
            <?= htmlspecialchars($msgError) ?>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
        <input type="text" id="filtroClienti" class="search-bar" placeholder="Cerca cognome, nome o telefono..." autofocus>
        <button type="button" class="btn-primary" onclick="apriModalCliente()" style="white-space: nowrap; cursor: pointer; padding: 9px 16px; font-weight: 600;">+ Nuovo Cliente</button>
    </div>

    <table class="client-table" id="tabellaClienti">
        <thead>
            <tr>
                <th>Cognome e Nome</th>
                <th>Telefono</th>
                <th>Data Registrazione</th>
                <th>Tot. Visite</th>
                <th>Note</th>
                <th style="text-align: right;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clienti as$c): ?>
                <tr class="client-row">
                    <td class="col-nome" style="font-weight: 600; color: #382c49;">
                        <?= htmlspecialchars($c['cognome'] . ' ' .$c['nome']) ?>
                    </td>
                    <td class="col-tel"><?= htmlspecialchars($c['telefono'] ?? '-') ?></td>
                    <td><?= !empty($c['creato_il']) ? date('d/m/Y', strtotime($c['creato_il'])) : '-' ?></td>
                    <td><span style="background: #f3eff9; padding: 2px 8px; border-radius: 12px; font-weight: bold;"><?= $c['totale_visite'] ?></span></td>
                    <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($c['note'] ?? '-') ?></td>
                    <td style="text-align: right; white-space: nowrap;">
                        <button type="button" class="btn-action-edit" onclick='apriModificaCliente(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)'>✏️ Modifica</button>
                        <form method="POST" action="clienti.php" style="display: inline-block;" onsubmit="return confirm('Eliminare definitivamente questo cliente e tutti i suoi appuntamenti?');">
                            <input type="hidden" name="azione" value="elimina">
                            <input type="hidden" name="id_cliente" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-action-del">🗑️ Elimina</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Modal Inserimento / Modifica Cliente -->
    <div class="modal-overlay" id="clienteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 3000;">
        <div class="modal-box" style="background: #fff; border-radius: 8px; width: 100%; max-width: 420px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <h2 id="modalClienteTitle" style="font-size: 18px; margin-top: 0; margin-bottom: 16px; color: #4a4063;">Nuovo Cliente</h2>
            <form method="POST" action="clienti.php">
                <input type="hidden" name="azione" value="salva">
                <input type="hidden" name="id_cliente" id="modalIdCliente" value="">

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #4a4063;">Cognome *</label>
                    <input type="text" name="cognome" id="modalCognome" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #4a4063;">Nome *</label>
                    <input type="text" name="nome" id="modalNome" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #4a4063;">Telefono</label>
                    <input type="text" name="telefono" id="modalTelefono" placeholder="Es. 3331234567" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #4a4063;">Note Scheda Cliente</label>
                    <textarea name="note" id="modalNote" rows="3" placeholder="Allergie, preferenze trattamenti, ecc." style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 13px;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="chiudiModalCliente()" style="background: #eee; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Annulla</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Salva Scheda</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modalCliente = document.getElementById('clienteModal');
        const modalTitle = document.getElementById('modalClienteTitle');
        const inputId = document.getElementById('modalIdCliente');
        const inputCognome = document.getElementById('modalCognome');
        const inputNome = document.getElementById('modalNome');
        const inputTelefono = document.getElementById('modalTelefono');
        const inputNote = document.getElementById('modalNote');
        const contatoreTotale = document.getElementById('totaleContatore');

        function apriModalCliente() {
            modalTitle.textContent = "Nuovo Cliente";
            inputId.value = "";
            inputCognome.value = "";
            inputNome.value = "";
            inputTelefono.value = "";
            inputNote.value = "";
            modalCliente.style.display = 'flex';
        }

        function apriModificaCliente(cliente) {
            modalTitle.textContent = "Modifica Cliente";
            inputId.value = cliente.id;
            inputCognome.value = cliente.cognome;
            inputNome.value = cliente.nome;
            inputTelefono.value = cliente.telefono || "";
            inputNote.value = cliente.note || "";
            modalCliente.style.display = 'flex';
        }

        function chiudiModalCliente() {
            modalCliente.style.display = 'none';
        }

        // Filtro rapido istantaneo ottimizzato per grandi elenchi
        document.getElementById('filtroClienti').addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.client-row');
            let visibili = 0;

            rows.forEach(row => {
                const nome = row.querySelector('.col-nome').textContent.toLowerCase();
                const tel = row.querySelector('.col-tel').textContent.toLowerCase();
                
                if (term === '' || nome.includes(term) || tel.includes(term)) {
                    row.style.display = '';
                    visibili++;
                } else {
                    row.style.display = 'none';
                }
            });

            contatoreTotale.textContent = visibili;
        });
    </script>
</body>
</html>