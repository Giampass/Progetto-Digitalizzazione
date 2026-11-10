
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$technicianId = requireTechnician();

/* Endpoint AJAX: restituisce le stampanti del cliente selezionato */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'printers') {
    header('Content-Type: application/json; charset=utf-8');

    $clientId = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT);

    if (!$clientId) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, brand, model, serial_number, counter_bw, counter_color
        FROM printers
        WHERE client_id = ?
        ORDER BY brand, model
    ");
    $stmt->execute([$clientId]);

    echo json_encode($stmt->fetchAll());
    exit;
}

/* Clienti */
$clients = $pdo->query("
    SELECT id, company_name
    FROM clients
    ORDER BY company_name
")->fetchAll();

$errors = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$form = [
    'client_id' => '',
    'printer_id' => '',
    'assistance_date' => date('Y-m-d\TH:i'),
    'reported_problem' => '',
    'error_code' => '',
    'diagnosis' => '',
    'work_performed' => '',
    'parts_replaced' => '',
    'counter_bw' => '0',
    'counter_color' => '0',
    'status' => 'pending',
    'duration_minutes' => '',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Richiesta non valida. Ricarica la pagina.');
    }

    foreach ($form as $key => $value) {
        if (isset($_POST[$key])) {
            $form[$key] = trim((string)$_POST[$key]);
        }
    }

    $clientId = filter_var($form['client_id'], FILTER_VALIDATE_INT);
    $printerId = filter_var($form['printer_id'], FILTER_VALIDATE_INT);

    $counterBwValid = ctype_digit($form['counter_bw']);
    $counterColorValid = ctype_digit($form['counter_color']);

    $duration = null;

    if ($form['duration_minutes'] !== '') {
        $duration = filter_var(
            $form['duration_minutes'],
            FILTER_VALIDATE_INT
        );
    }

    $validStatuses = [
        'pending',
        'in_progress',
        'completed',
        'waiting_parts'
    ];

    if (!$clientId) {
        $errors[] = 'Seleziona un cliente.';
    }

    if (!$printerId) {
        $errors[] = 'Seleziona una stampante.';
    }

    if ($form['reported_problem'] === '') {
        $errors[] = 'Inserisci il problema segnalato.';
    }

    if (!$counterBwValid || !$counterColorValid) {
        $errors[] = 'Inserisci contatori validi.';
    }

    if (!in_array($form['status'], $validStatuses, true)) {
        $errors[] = 'Seleziona uno stato valido.';
    }

    if ($form['duration_minutes'] !== '' &&
        ($duration === false || $duration < 0)) {
        $errors[] = 'Inserisci una durata valida.';
    }

    $date = DateTime::createFromFormat('Y-m-d\TH:i', $form['assistance_date']);

    if (!$date || $date->format('Y-m-d\TH:i') !== $form['assistance_date']) {
        $errors[] = 'Inserisci una data valida.';
    }

    if ($clientId && $printerId) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM printers
            WHERE id = ? AND client_id = ?
        ");
        $stmt->execute([$printerId, $clientId]);

        if (!$stmt->fetch()) {
            $errors[] = 'La stampante selezionata non appartiene al cliente.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO assistances (
                    client_id,
                    printer_id,
                    technician_id,
                    assistance_date,
                    reported_problem,
                    error_code,
                    diagnosis,
                    work_performed,
                    parts_replaced,
                    counter_bw,
                    counter_color,
                    status,
                    duration_minutes,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $clientId,
                $printerId,
                $technicianId,
                $date->format('Y-m-d H:i:s'),
                $form['reported_problem'],
                $form['error_code'] ?: null,
                $form['diagnosis'] ?: null,
                $form['work_performed'] ?: null,
                $form['parts_replaced'] ?: null,
                (int)$form['counter_bw'],
                (int)$form['counter_color'],
                $form['status'],
                $duration === false ? null : $duration,
                $form['notes'] ?: null
            ]);

            $assistanceId = (int)$pdo->lastInsertId();

            /* Aggiorna i contatori attuali della stampante */
            $stmt = $pdo->prepare("
                UPDATE printers
                SET counter_bw = ?, counter_color = ?
                WHERE id = ?
            ");
            $stmt->execute([
                (int)$form['counter_bw'],
                (int)$form['counter_color'],
                $printerId
            ]);

            logActivity(
                $pdo,
                'CREATE',
                'assistance',
                $assistanceId,
                'Nuova assistenza registrata per la stampante ID ' . $printerId
            );

            $pdo->commit();

            header('Location: index.php?created=1');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = 'Errore durante la registrazione dell’intervento.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova assistenza | TechSupport</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Nuova assistenza</h1>
                <p>Registra un nuovo intervento tecnico</p>
            </div>
            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="section-header">
                <div>
                    <h2>Dati dell'intervento</h2>
                    <p>Compila le informazioni richieste</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="client-form">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="detail-card">
                    <h3>Cliente e stampante</h3>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="client_id">Cliente *</label>

                            <select name="client_id" id="client_id" required>
                                <option value="">Seleziona cliente</option>

                                <?php foreach ($clients as $client): ?>
                                    <option
                                        value="<?= (int)$client['id'] ?>"
                                        <?= (string)$form['client_id'] === (string)$client['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="printer_id">Stampante *</label>

                            <select name="printer_id" id="printer_id" required disabled>
                                <option value="">Seleziona prima un cliente</option>
                            </select>

                            <small id="printer-info" class="field-help"></small>
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="assistance_date">Data e ora *</label>
                            <input
                                type="datetime-local"
                                name="assistance_date"
                                id="assistance_date"
                                value="<?= htmlspecialchars($form['assistance_date']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Tecnico assegnato</label>
                            <input
                                type="text"
                                value="<?= htmlspecialchars($currentTechnician['name'] ?? 'Non selezionato') ?>"
                                disabled
                            >
                        </div>

                    </div>
                </div>

                <div class="detail-card">
                    <h3>Problema e diagnosi</h3>

                    <div class="form-group">
                        <label for="reported_problem">Problema segnalato *</label>
                        <textarea name="reported_problem" id="reported_problem"
                            rows="3" required><?= htmlspecialchars($form['reported_problem']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="error_code">Codice errore</label>
                        <input type="text" name="error_code" id="error_code"
                            value="<?= htmlspecialchars($form['error_code']) ?>"
                            placeholder="Es. L4-18, H4-01...">
                    </div>

                    <div class="form-group">
                        <label for="diagnosis">Diagnosi tecnica</label>
                        <textarea name="diagnosis" id="diagnosis"
                            rows="3"><?= htmlspecialchars($form['diagnosis']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="work_performed">Lavoro effettuato</label>
                        <textarea name="work_performed" id="work_performed"
                            rows="4"><?= htmlspecialchars($form['work_performed']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="parts_replaced">Ricambi sostituiti</label>
                        <textarea name="parts_replaced" id="parts_replaced"
                            rows="2"><?= htmlspecialchars($form['parts_replaced']) ?></textarea>
                    </div>
                </div>

                <div class="detail-card">
                    <h3>Contatori e stato</h3>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="counter_bw">Contatore B/N</label>
                            <input type="number" min="0" name="counter_bw"
                                id="counter_bw"
                                value="<?= htmlspecialchars($form['counter_bw']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="counter_color">Contatore colore</label>
                            <input type="number" min="0" name="counter_color"
                                id="counter_color"
                                value="<?= htmlspecialchars($form['counter_color']) ?>">
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="status">Stato intervento</label>
                            <select name="status" id="status">
                                <option value="pending" <?= $form['status'] === 'pending' ? 'selected' : '' ?>>In attesa</option>
                                <option value="in_progress" <?= $form['status'] === 'in_progress' ? 'selected' : '' ?>>In corso</option>
                                <option value="completed" <?= $form['status'] === 'completed' ? 'selected' : '' ?>>Completata</option>
                                <option value="waiting_parts" <?= $form['status'] === 'waiting_parts' ? 'selected' : '' ?>>In attesa ricambi</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="duration_minutes">Durata (minuti)</label>
                            <input type="number" min="0" name="duration_minutes"
                                id="duration_minutes"
                                value="<?= htmlspecialchars($form['duration_minutes']) ?>">
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="notes">Note aggiuntive</label>
                        <textarea name="notes" id="notes"
                            rows="3"><?= htmlspecialchars($form['notes']) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-secondary">Annulla</a>
                    <button type="submit" class="btn-primary">Salva intervento</button>
                </div>

            </form>

        </section>
    </main>
</div>

<script>
const clientSelect = document.getElementById('client_id');
const printerSelect = document.getElementById('printer_id');
const printerInfo = document.getElementById('printer-info');
const counterBw = document.getElementById('counter_bw');
const counterColor = document.getElementById('counter_color');

async function loadPrinters(clientId, selectedPrinter = null) {
    printerSelect.innerHTML = '<option value="">Caricamento...</option>';
    printerSelect.disabled = true;
    printerInfo.textContent = '';

    if (!clientId) {
        printerSelect.innerHTML = '<option value="">Seleziona prima un cliente</option>';
        return;
    }

    try {
        const response = await fetch(
            'create.php?ajax=printers&client_id=' + encodeURIComponent(clientId)
        );

        if (!response.ok) {
            throw new Error('Errore durante il caricamento');
        }

        const printers = await response.json();

        printerSelect.innerHTML = '';

        if (printers.length === 0) {
            printerSelect.innerHTML = '<option value="">Nessuna stampante associata</option>';
            return;
        }

        printerSelect.add(new Option('Seleziona stampante', ''));

        printers.forEach(printer => {
            const label = printer.brand + ' ' + printer.model +
                (printer.serial_number ? ' — ' + printer.serial_number : '');

            const option = new Option(label, printer.id);
            option.dataset.bw = printer.counter_bw;
            option.dataset.color = printer.counter_color;

            printerSelect.add(option);
        });

        printerSelect.disabled = false;

        if (selectedPrinter) {
            printerSelect.value = String(selectedPrinter);
        }

        updateCounters();

    } catch (error) {
        printerSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
        printerInfo.textContent = 'Impossibile caricare le stampanti.';
    }
}

function updateCounters() {
    const option = printerSelect.selectedOptions[0];

    if (!option || !option.value) {
        printerInfo.textContent = '';
        return;
    }

    counterBw.value = option.dataset.bw || 0;
    counterColor.value = option.dataset.color || 0;

    printerInfo.textContent =
        'Contatori attuali: B/N ' + counterBw.value +
        ' — Colore ' + counterColor.value;
}

clientSelect.addEventListener('change', function() {
    loadPrinters(this.value);
});

printerSelect.addEventListener('change', updateCounters);

<?php if (!empty($form['client_id'])): ?>
loadPrinters(
    <?= json_encode((string)$form['client_id']) ?>,
    <?= json_encode((string)$form['printer_id']) ?>
);
<?php endif; ?>
</script>

</body>
</html>
