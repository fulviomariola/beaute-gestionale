<?php
require_once 'config.php';
requireAdminLogin();
$dataSelezionata = $_GET['data'] ?? date('Y-m-d');
$stmtCabine = $pdo->query("SELECT * FROM `cabine` WHERE `attiva` = 1 ORDER BY `numero` ASC");
$cabine = $stmtCabine->fetchAll();
$stmtTrattamenti = $pdo->query("SELECT * FROM `trattamenti` WHERE `attivo` = 1 ORDER BY `nome` ASC");
$trattamenti = $stmtTrattamenti->fetchAll();

$stmtApp = $pdo->prepare("
    SELECT a.*, c.nome AS cliente_nome, c.cognome AS cliente_cognome, c.telefono AS cliente_tel, t.nome AS trattamento_nome, t.durata_minuti_default
    FROM `appuntamenti` a
    JOIN `clienti` c ON a.id_cliente = c.id
    LEFT JOIN `trattamenti` t ON a.id_trattamento = t.id
    WHERE a.data_appuntamento = :data AND a.stato != 'cancellato'
    ORDER BY a.ora_inizio ASC
");

$stmtApp->execute([':data' => $dataSelezionata]);
$appuntamenti = $stmtApp->fetchAll();
$agendaMap = [];

foreach ($appuntamenti as $app) {
    $oraSlot = substr($app['ora_inizio'], 0, 5);
    $agendaMap[$app['id_cabina']][$oraSlot][] = $app;
}
$fasceOrarie = [];
for ($h = 8; $h <= 20; $h++) {
    foreach (['00', '15', '30', '45'] as $m) {
        if ($h == 20 && $m !== '00')
            break;
        $fasceOrarie[] = sprintf('%02d:%s', $h, $m);
    }
}
$fasceFine = array_merge($fasceOrarie, ['20:15']);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - La Beauté</title>
    <link rel="stylesheet" href="css/admin-theme.css">
    <style>
    .agenda-grid {
        display: grid;
        grid-template-columns: 60px repeat(<?=count($cabine) ?>, 1fr);
        gap: 1px;
        background: var(--border-color);
        border: 1px solid var(--border-color);
    }

    .grid-header {
        background: #f8f5fa;
        padding: 8px;
        text-align: center;
        font-weight: bold;
        font-size: 13px;
    }

    .time-col {
        background: #fff;
    }

    .cabin-col {
        background: #fff;
        display: flex;
        flex-direction: column;
    }

    .time-slot-label,
    .slot-cell {
        height: 48px;
        box-sizing: border-box;
    }

    .time-slot-label {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 4px;
        font-size: 10px;
        color: #666;
        background: #fafafa;
        border-bottom: 1px solid #eee;
    }

    .time-slot-label.quarter {
        font-size: 9px;
        color: #aaa;
    }

    .slot-cell {
        position: relative;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.15s;
        overflow: visible;
    }

    .slot-cell:hover {
        background: #fdfbff;
    }

    .slot-cell.hour-border {
        border-bottom: 2px solid #bbb;
    }

    .appointment-card {
        position: absolute;
        top: 1px;
        left: 1px;
        right: 1px;
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 4px 6px;
        font-size: 12px;
        line-height: 1.3;
        z-index: 5;
        overflow: hidden;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .appointment-card:hover {
        background: #bbdefb;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
    }

    .appointment-card .client-name {
        font-weight: bold;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .appointment-card .client-tel {
        color: #0d47a1;
        font-weight: normal;
    }

    .appointment-card .service-name {
        color: #555;
        font-size: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .appointment-card .service-time {
        color: #1976d2;
        font-weight: 600;
        font-size: 10px;
    }

    .edit-icon {
        font-size: 12px;
        opacity: 0.7;
    }

    .slot-cell.inline-active {
        height: auto !important;
        min-height: 280px;
        z-index: 100;
        background: #fff;
        border: 2px solid #2196f3;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        padding: 8px;
        overflow: visible;
        border-radius: 4px;
    }

    .inline-form {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .inline-input,
    .inline-select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 13px;
        font-family: inherit;
        box-sizing: border-box;
    }

    .inline-input:focus,
    .inline-select:focus {
        border-color: #2196f3;
        outline: none;
        box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.15);
    }

    .inline-time-row {
        display: flex;
        gap: 6px;
    }

    .inline-time-row>* {
        flex: 1;
    }

    .inline-time-label {
        font-size: 11px;
        color: #666;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .inline-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #2196f3;
        border-top: none;
        max-height: 140px;
        overflow-y: auto;
        z-index: 101;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        display: none;
        border-radius: 0 0 4px 4px;
    }

    .inline-suggestion-item {
        padding: 6px 8px;
        font-size: 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
    }

    .inline-suggestion-item:hover {
        background: #e3f2fd;
    }

    .inline-suggestion-item:last-child {
        border-bottom: none;
    }

    .inline-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px solid #eee;
    }

    .btn-inline {
        border: none;
        border-radius: 4px;
        padding: 6px 12px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-save {
        background: #4caf50;
        color: white;
    }

    .btn-save:hover {
        background: #43a047;
    }

    .btn-cancel {
        background: #f44336;
        color: white;
    }

    .btn-cancel:hover {
        background: #e53935;
    }

    .btn-delete {
        background: #ff9800;
        color: white;
        font-size: 16px;
        padding: 6px 10px;
    }

    .btn-delete:hover {
        background: #fb8c00;
    }

    .mobile-tabs {
        display: none;
        gap: 4px;
        padding: 8px;
        background: #fff;
        border-bottom: 1px solid var(--border-color);
        overflow-x: auto;
    }

    .mobile-tabs button {
        padding: 8px 14px;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        background: #fff;
        cursor: pointer;
        font-size: 13px;
        white-space: nowrap;
        font-weight: 600;
    }

    .mobile-tabs button.active {
        background: #4a4063;
        color: #fff;
        border-color: #4a4063;
    }

    @media (max-width: 768px) {
        .agenda-grid {
            grid-template-columns: 50px 1fr;
        }

        .grid-header:not(.time-header):not(.active-header),
        .cabin-col:not(.active-cabin) {
            display: none;
        }

        .mobile-tabs {
            display: flex;
        }
    }
    </style>
</head>

<body>
    <header class="top-bar">
        <div>
            <h1>La Beauté — Agenda</h1>
            <form method="GET" style="display: inline-block; margin-top: 5px;">
                <input type="date" name="data" value="<?= htmlspecialchars($dataSelezionata) ?>"
                    onchange="this.form.submit()">
            </form>
        </div>
        <div class="actions" style="display: flex; gap: 10px; align-items: center;">
            <a href="clienti.php">👥 Clienti</a>
            <a href="logout.php"
                style="background: #a499a8; padding: 5px 10px; border-radius: 4px; color: white; text-decoration: none;">Esci</a>
        </div>
    </header>
    <div class="mobile-tabs">
        <?php foreach ($cabine as $idx => $cab): ?>
        <button type="button" class="<?= $idx === 0 ? 'active' : '' ?>" onclick="switchCabin(<?= $cab['id'] ?>, this)">
            <?= htmlspecialchars($cab['nome']) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div class="agenda-grid">
        <div class="grid-header time-header">Orario</div>
        <?php foreach ($cabine as $idx => $cab): ?>
        <div class="grid-header <?= $idx === 0 ? 'active-header' : '' ?>" data-cabin-id="<?= $cab['id'] ?>">
            <?= htmlspecialchars($cab['nome']) ?>
        </div>
        <?php endforeach; ?>
        <div class="time-col">
            <?php foreach ($fasceOrarie as $ora): ?>
            <div class="time-slot-label <?= in_array(substr($ora, -2), ['15', '45']) ? 'quarter' : '' ?>"><?= $ora ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php foreach ($cabine as $idx => $cab): ?>
        <div class="cabin-col <?= $idx === 0 ? 'active-cabin' : '' ?>" data-cabin-id="<?= $cab['id'] ?>">
            <?php foreach ($fasceOrarie as $ora): ?>
            <div class="slot-cell hour-border" data-cabina="<?= $cab['id'] ?>" data-ora="<?= $ora ?>"
                onclick="openNewForm(this)">
                <?php if (!empty($agendaMap[$cab['id']][$ora])): ?>
                <?php foreach ($agendaMap[$cab['id']][$ora] as $app): ?>
                <?php
                                $minutiInizio = strtotime($app['ora_inizio']);
                                $minutiFine = strtotime($app['ora_fine']);
                                $durataMinuti = max(15, ($minutiFine - $minutiInizio) / 60);
                                $altezzaPx = ($durataMinuti / 15) * 48;
                                ?>
                <div class="appointment-card" style="height: <?= $altezzaPx ?>px;"
                    onclick="event.stopPropagation(); openEditForm(this.parentElement, <?= htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8') ?>)">
                    <div class="client-name">
                        <?= htmlspecialchars(trim($app['cliente_cognome'] . ' ' . $app['cliente_nome'])) ?>
                        <?php if (!empty($app['cliente_tel'])): ?> <strong
                            class="client-tel"><?= htmlspecialchars($app['cliente_tel']) ?></strong><?php endif; ?>
                    </div>
                    <div class="service-name">
                        <span><?= htmlspecialchars($app['trattamento_nome'] ?? 'Tratt.') ?></span>
                        <span class="edit-icon">✏️</span>
                    </div>
                    <div class="service-time"><?= substr($app['ora_inizio'], 0, 5) ?> -
                        <?= substr($app['ora_fine'], 0, 5) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
    const trattamentiData = <?= json_encode($trattamenti) ?>;
    const fasceOrarie = <?= json_encode($fasceOrarie) ?>;
    const fasceFine = <?= json_encode($fasceFine) ?>;
    const dataAppuntamento = "<?= $dataSelezionata ?>";
    const csrfToken = "<?= csrfToken() ?>";

    let activeFormCell = null;
    let manualEndTimeSet = false; // Flag per tracciare se l'utente ha modificato manualmente l'ora di fine
    let dupTimer = null;
    let dupSeq = 0; // contatore per ignorare risposte arrivate in ritardo

function checkDuplicati() {
    const warn = document.getElementById('inline-dup-warning');
    if (!warn) return;

    const hide = () => { warn.style.display = 'none'; };

    // Cliente già scelto dall'elenco: nessun avviso
    if (document.getElementById('inline-id-cliente').value) { hide(); return; }

    const cognome = document.getElementById('inline-cliente-search').value.trim();
    const nome    = document.getElementById('inline-nome').value.trim();
    const tel     = document.getElementById('inline-telefono').value.trim();

    const queries = [];
    if (cognome.length >= 2) queries.push(cognome);
    if (tel.length >= 3)     queries.push(tel);
    if (!queries.length) { hide(); return; }

    const mySeq = ++dupSeq;
    clearTimeout(dupTimer);

    // Confronto senza distinguere maiuscole e accenti
    const uguali = (a, b) => (a || '').trim().localeCompare((b || '').trim(), 'it', { sensitivity: 'base' }) === 0;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

    dupTimer = setTimeout(() => {
        Promise.all(queries.map(q =>
            fetch(`api/cerca_cliente.php?q=${encodeURIComponent(q)}`)
                .then(r => r.ok ? r.json() : [])
        )).then(results => {
            if (mySeq !== dupSeq) return;
            const trovati = results.flat().filter(c => c && c.id);

            // 1) stesso cognome E stesso nome
            const stessoNome = nome !== '' ? trovati.find(c => uguali(c.cognome, cognome) && uguali(c.nome, nome)) : null;
            // 2) stesso telefono
            const stessoTel = tel.length >= 3 ? trovati.find(c => c.telefono && c.telefono.replace(/\s/g, '') === tel.replace(/\s/g, '')) : null;

            if (stessoNome) {
                warn.innerHTML = `⚠️ <strong>Attenzione:</strong> esiste già un cliente chiamato <strong>${esc(stessoNome.cognome)} ${esc(stessoNome.nome)}</strong>${stessoNome.telefono ? ' (tel. ' + esc(stessoNome.telefono) + ')' : ''}. Se è la stessa persona, cliccala nell'elenco; se è un omonimo, puoi salvare normalmente.`;
                warn.style.display = 'block';
            } else if (stessoTel) {
                warn.innerHTML = `⚠️ <strong>Attenzione:</strong> questo telefono è già registrato per <strong>${esc(stessoTel.cognome)} ${esc(stessoTel.nome)}</strong>. Se è la stessa persona, cliccala nell'elenco.`;
                warn.style.display = 'block';
            } else {
                hide();
            }
        }).catch(() => {
            if (mySeq !== dupSeq) return;
            hide();
        });
    }, 300);
}

    function computeEnd(start, minuti) {
        let [h, m] = start.split(':').map(Number);
        m += minuti;
        while (m >= 60) {
            h += 1;
            m -= 60;
        }
        let fine = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        if (fine > '20:15') fine = '20:15';
        return fine;
    }

    function openNewForm(cell) {
        if (activeFormCell === cell) return;
        if (activeFormCell) closeInlineForm(activeFormCell);
        if (cell.querySelector('.appointment-card')) return;
        cell.dataset.originalHtml = cell.innerHTML;
        activeFormCell = cell;
        cell.classList.add('inline-active');
        const oraInizio = cell.dataset.ora;
        const oraFine = computeEnd(oraInizio, 30);
        manualEndTimeSet = false;
        renderInlineForm(cell, cell.dataset.cabina, oraInizio, oraFine, false, null);
    }

    function openEditForm(cell, appData) {
        if (activeFormCell && activeFormCell !== cell) closeInlineForm(activeFormCell);
        cell.dataset.originalHtml = cell.innerHTML;
        activeFormCell = cell;
        cell.classList.add('inline-active');
        manualEndTimeSet = true; // In modifica, consideriamo l'ora di fine come "manuale"
        renderInlineForm(cell, cell.dataset.cabina, appData.ora_inizio.substring(0, 5), appData.ora_fine.substring(0,
            5), true, appData);
    }

    function renderInlineForm(cell, cabinaId, oraInizio, oraFine, isEdit, appData) {
        appData = appData || {};
        let opzioniTrattamenti = '<option value="">-- Seleziona --</option>';
        trattamentiData.forEach(t => {
            const selected = (isEdit && appData.id_trattamento == t.id) ? 'selected' : '';
            opzioniTrattamenti +=
                `<option value="${t.id}" data-durata="${t.durata_minuti_default}" ${selected}>${t.nome}</option>`;
        });
        let selectStart = '<select class="inline-select" id="inline-ora-inizio" onchange="handleStartTimeChange()">';
        fasceOrarie.forEach(f => {
            selectStart += `<option value="${f}" ${f === oraInizio ? 'selected' : ''}>${f}</option>`;
        });
        selectStart += '</select>';
        let selectEnd = '<select class="inline-select" id="inline-ora-fine" onchange="handleEndTimeChange()">';
        fasceFine.forEach(f => {
            selectEnd += `<option value="${f}" ${f === oraFine ? 'selected' : ''}>${f}</option>`;
        });
        selectEnd += '</select>';
        const clienteVal = isEdit ? (appData.cliente_cognome || '') : '';
        const nomeVal = isEdit ? (appData.cliente_nome || '') : '';
        const telVal = isEdit ? (appData.cliente_tel || '') : '';
        const noteVal = isEdit ? (appData.note_trattamento || '') : '';
        const deleteBtn = isEdit ?
            `<button type="button" class="btn-inline btn-delete" onclick="deleteAppointment()" title="Elimina">🗑️</button>` :
            '';
        cell.innerHTML = `
                <div class="inline-form" onclick="event.stopPropagation()">
                <input type="hidden" id="inline-cabina" value="${cabinaId}">
                <input type="hidden" id="inline-id-cliente" value="${isEdit ? appData.id_cliente : ''}">
                <input type="hidden" id="inline-id-app" value="${isEdit ? appData.id : ''}">
                <div>
                <div class="inline-time-label">⏰ Orario</div>
                <div class="inline-time-row">
                ${selectStart}
                ${selectEnd}
                </div>
                </div>
                <div style="position: relative;">
                <div class="inline-time-label">👤 Cognome</div>
                <input type="text" class="inline-input" id="inline-cliente-search" placeholder="Cerca o scrivi nuovo..." value="${clienteVal}" autocomplete="off">
                <div class="inline-suggestions" id="inline-suggestions"></div>
                </div>
                <div>
                <div class="inline-time-label">👤 Nome</div>
                <input type="text" class="inline-input" id="inline-nome" placeholder="Nome" value="${nomeVal}" autocomplete="off">
                </div>                
                <div>
                <div class="inline-time-label">📞 Telefono</div>
                <input type="text" class="inline-input" id="inline-telefono" placeholder="Es. 3331234567" value="${telVal}">
                </div>
                <div>
                <div class="inline-time-label">💆 Trattamento</div>
                <select class="inline-select" id="inline-trattamento" onchange="updateOraFine()">
                ${opzioniTrattamenti}
                </select>
                </div>
                <div>
                <div class="inline-time-label">📝 Note</div>
                <input type="text" class="inline-input" id="inline-note" placeholder="Opzionale..." value="${noteVal}">
                </div>
                <div>
                <div id="inline-dup-warning" style="display:none; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:4px; padding:6px 8px; font-size:11px; line-height:1.4;"></div>
                <div class="inline-actions">
                ${deleteBtn}
                <div style="display: flex; gap: 6px; margin-left: auto;">
                <button type="button" class="btn-inline btn-cancel" onclick="closeInlineForm(activeFormCell)" title="Annulla (ESC)">✖</button>
                <button type="button" class="btn-inline btn-save" onclick="salvaAppuntamento()" title="Salva">💾 Salva</button>
                </div>
                </div>
                </div>
                `;
        setTimeout(() => {
            document.getElementById('inline-cliente-search').focus();
            setupAutocomplete();
        }, 100);
    }

    function closeInlineForm(cell) {
        if (!cell) return;
        cell.classList.remove('inline-active');
        if (cell.dataset.originalHtml !== undefined) {
            cell.innerHTML = cell.dataset.originalHtml;
            delete cell.dataset.originalHtml;
        } else {
            cell.innerHTML = '';
        }
        activeFormCell = null;
        manualEndTimeSet = false;
    }

    function handleStartTimeChange() {
        // Se l'utente cambia l'ora di inizio, resettiamo il flag
        manualEndTimeSet = false;
        updateOraFine();
    }

    function handleEndTimeChange() {
        // L'utente ha modificato manualmente l'ora di fine
        manualEndTimeSet = true;
    }

    function updateOraFine() {
        // Se l'utente ha impostato manualmente l'ora di fine, non la sovrascriviamo
        if (manualEndTimeSet) return;

        const selectTratt = document.getElementById('inline-trattamento');
        const durata = selectTratt.options[selectTratt.selectedIndex].dataset.durata;
        const oraInizio = document.getElementById('inline-ora-inizio').value;
        if (!durata || !oraInizio) return;
        const oraFine = computeEnd(oraInizio, parseInt(durata));
        const selectFine = document.getElementById('inline-ora-fine');
        for (let i = 0; i < selectFine.options.length; i++) {
            if (selectFine.options[i].value === oraFine) {
                selectFine.selectedIndex = i;
                break;
            }
        }
    }

    function setupAutocomplete() {
        const input = document.getElementById('inline-cliente-search');
        const suggestionsBox = document.getElementById('inline-suggestions');
        const idClienteHidden = document.getElementById('inline-id-cliente');
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);
        
        // Chiude l'elenco dei suggerimenti quando esci dal campo (il ritardo permette di cliccare un suggerimento)
        newInput.addEventListener('blur', () => setTimeout(() => { suggestionsBox.style.display = 'none'; }, 250));

        newInput.addEventListener('input', function() {
            const query = this.value.trim();
            idClienteHidden.value = '';

            checkDuplicati(); 

            // Parsing intelligente: cerca un numero di telefono alla fine del testo
            const match = query.match(/^(.*?)\s+([0-9+\s\-()]{6,})$/);
            if (match) {
                this.value = match[1].trim();
                document.getElementById('inline-telefono').value = match[2].trim();
            }
            if (query.length < 2) { suggestionsBox.style.display = 'none'; return; }
            fetch(`api/cerca_cliente.php?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    suggestionsBox.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(c => {
                            const item = document.createElement('div');
                            item.className = 'inline-suggestion-item';
                            item.innerHTML =
                                `<strong>${c.cognome} ${c.nome}</strong> <span style="color:#888;">${c.telefono || ''}</span>`;
                            item.onclick = () => {
                                idClienteHidden.value = c.id;
                                newInput.value = c.cognome;
                                document.getElementById('inline-nome').value = c.nome || '';
                                document.getElementById('inline-telefono').value = c.telefono || '';
                                suggestionsBox.style.display = 'none';
                                document.getElementById('inline-dup-warning').style.display = 'none';
                            };
                            suggestionsBox.appendChild(item);
                        });
                        suggestionsBox.style.display = 'block';
                    } else {
                        suggestionsBox.style.display = 'none';
                    }
                });
        });
        document.getElementById('inline-telefono').addEventListener('input', checkDuplicati);
        document.getElementById('inline-nome').addEventListener('input', function() {
            idClienteHidden.value = '';
            suggestionsBox.style.display = 'none';
            checkDuplicati();
        });
    }

    function salvaAppuntamento() {
        const idCliente = document.getElementById('inline-id-cliente').value;
        const cognome = document.getElementById('inline-cliente-search').value.trim();
        const nome = document.getElementById('inline-nome').value.trim();
        const idTrattamento = document.getElementById('inline-trattamento').value;
        const note = document.getElementById('inline-note').value;
        const telefono = document.getElementById('inline-telefono').value.trim();
        const oraInizio = document.getElementById('inline-ora-inizio').value;
        const oraFine = document.getElementById('inline-ora-fine').value;
        const cabina = document.getElementById('inline-cabina').value;
        const idApp = document.getElementById('inline-id-app').value;
        
        if (!idCliente && (!cognome || !nome)) {
            alert('Per un nuovo cliente inserisci sia cognome che nome');
            return;
        }
        if (idCliente && !cognome) {
            alert('Inserisci un cliente');
            return;
        }
        if (oraInizio >= oraFine) {
            alert("L'orario di fine deve essere successivo all'inizio");
            return;
        }
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        if (idApp) formData.append('id_appuntamento', idApp);

        formData.append('id_cabina', cabina);
        formData.append('data_appuntamento', dataAppuntamento);
        formData.append('ora_inizio', oraInizio);
        formData.append('ora_fine', oraFine);
        formData.append('id_trattamento', idTrattamento);
        formData.append('note_trattamento', note);
        formData.append('nuovo_telefono', telefono); // Sempre inviato, anche per clienti esistenti
        
        if (!idCliente) {
            formData.append('nuovo_cognome', cognome);
            formData.append('nuovo_nome', nome);
        } else {
            formData.append('id_cliente', idCliente);
        }

        fetch('api/salva_appuntamento.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json().then(res => ({ status: r.status, res })))
            .then(({ status, res }) => {
                if (res.success) window.location.reload();
                else if (status === 401) { alert(res.message); window.location.href = 'index.php'; }
                else alert(res.message);
            })
            .catch(() => alert('Errore di connessione.'));
    }

    function deleteAppointment() {
        const idApp = document.getElementById('inline-id-app').value;
        if (!idApp || !confirm("Eliminare questo appuntamento?")) return;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('id_appuntamento', idApp);
        fetch('api/cancella_appuntamento.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json().then(res => ({ status: r.status, res })))
            .then(({ status, res }) => {
                if (res.success) window.location.reload();
                else if (status === 401) { alert(res.message); window.location.href = 'index.php'; }
                else alert(res.message);
            });
    }

    function switchCabin(cabinId, btn) {
        document.querySelectorAll('.mobile-tabs button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.cabin-col').forEach(col =>
            col.classList.toggle('active-cabin', col.dataset.cabinId == cabinId));
        document.querySelectorAll('.grid-header[data-cabin-id]').forEach(h =>
            h.classList.toggle('active-header', h.dataset.cabinId == cabinId));
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && activeFormCell) closeInlineForm(activeFormCell);
    });

    document.addEventListener('click', function(e) {
        if (activeFormCell && !activeFormCell.contains(e.target)) {
            closeInlineForm(activeFormCell);
        }
    });
    </script>
</body>

</html>