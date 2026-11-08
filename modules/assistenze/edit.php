
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$editorId = requireTechnician();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM assistances WHERE id = ?");
$stmt->execute([$id]);
$assistance = $stmt->fetch();

if (!$assistance) {
    http_response_code(404);
    die('Intervento non trovato.');
}

/* Endpoint AJAX */
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

$clients = $pdo->query("
    SELECT id, company_name FROM clients ORDER BY company_name
")->fetchAll();

$errors = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$form = $assistance;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Richiesta non valida. Ricarica la pagina.');
    }

    $clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
    $printerId = filter_var($_POST['printer_id'] ?? null, FILTER_VALIDATE_INT);

    $dateRaw = trim($_POST['assistance_date'] ?? '');
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $dateRaw);

    $reportedProblem = trim($_POST['reported_problem'] ?? '');
    $errorCode = trim($_POST['error_code'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $workPerformed = trim($_POST['work_performed'] ?? '');
    $partsReplaced = trim($_POST['parts_replaced'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $counterBwRaw = trim($_POST['counter_bw'] ?? '');
    $counterColorRaw = trim($_POST['counter_color'] ?? '');
    $durationRaw = trim($_POST['duration_minutes'] ?? '');

    $status = $_POST['status'] ?? '';

    $validStatuses = [
        'pending',
        'in_progress',
        'completed',
        'waiting_parts'
    ];

    if (!$clientId) $errors[] = 'Seleziona un cliente.';
    if (!$printerId) $errors[] = 'Seleziona una stampante.';
    if ($reportedProblem === '') $errors[] = 'Inserisci il problema segnalato.';

    if (!$date || $date->format('Y-m-d\TH:i') !== $dateRaw) {
        $errors[] = 'Inserisci una data valida.';
    }

    if (!ctype_digit($counterBwRaw) || !ctype_digit($counterColorRaw)) {
        $errors[] = 'Inserisci contatori validi.';
    }

    if (!in_array($status, $validStatuses, true)) {
        $errors[] = 'Seleziona uno stato valido.';
    }

    $duration = null;

    if ($durationRaw !== '') {
        $duration = filter_var($durationRaw, FILTER_VALIDATE_INT);

        if ($duration === false || $duration < 0) {
            $errors[] = 'Inserisci una durata valida.';
        }
    }

    if ($clientId && $printerId) {
        $stmt = $pdo->prepare("
            SELECT id FROM printers
            WHERE id = ? AND client_id = ?
        ");
        $stmt->execute([$printerId, $clientId]);

        if (!$stmt->fetch()) {
            $errors[] = 'La stampante non appartiene al cliente selezionato.';
        }
    }

    $form = array_merge($form, [
        'client_id' => $clientId ?: '',
        'printer_id' => $printerId ?: '',
        'assistance_date' => $dateRaw,
        'reported_problem' => $reportedProblem,
        'error_code' => $errorCode,
        'diagnosis' => $diagnosis,
        'work_performed' => $workPerformed,
        'parts_replaced' => $partsReplaced,
        'counter_bw' => $counterBwRaw,
        'counter_color' => $counterColorRaw,
        'status' => $status,
        'duration_minutes' => $durationRaw,
        'notes' => $notes
    ]);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE assistances SET
                    client_id = ?,
                    printer_id = ?,
                    assistance_date = ?,
                    reported_problem = ?,
                    error_code = ?,
                    diagnosis = ?,
                    work_performed = ?,
                    parts_replaced = ?,
                    counter_bw = ?,
                    counter_color = ?,
                    status = ?,
                    duration_minutes = ?,
                    notes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $clientId,
                $printerId,
                $date->format('Y-m-d H:i:s'),
                $reportedProblem,
                $errorCode ?: null,
                $diagnosis ?: null,
                $workPerformed ?: null,
                $partsReplaced ?: null,
                (int)$counterBwRaw,
                (int)$counterColorRaw,
                $status,
                $duration,
                $notes ?: null,
                $id
            ]);

            logActivity(
                $pdo,
                'UPDATE',
                'assistance',
                $id,
                'Intervento aggiornato dal tecnico ID ' . $editorId
            );

            $pdo->commit();

            header('Location: view.php?id=' . $id . '&updated=1');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = 'Errore durante la modifica dell’intervento.';
        }
    }
}

$datetimeValue = str_replace(' ', 'T', substr($form['assistance_date'], 0, 16));
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica assistenza | TechSupport</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Modifica assistenza #<?= (int)$id ?></h1>
                <p>Aggiorna i dati dell'intervento</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

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
                            <label>Cliente *</label>
                            <select name="client_id" id="client_id" required>
                                <option value="">Seleziona cliente</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>"
                                        <?= (int)$form['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Stampante *</label>
                            <select name="printer_id" id="printer_id" required>
                                <option value="">Caricamento stampanti...</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Data e ora *</label>
                            <input type="datetime-local" name="assistance_date"
                                value="<?= htmlspecialchars($datetimeValue) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Tecnico originale</label>
                            <input type="text" value="<?= htmlspecialchars(
                                $assistance['technician_id']
                                    ? ($pdo->query("SELECT name FROM technicians WHERE id = " . (int)$assistance['technician_id'])->fetchColumn() ?: 'Non assegnato')
                                    : 'Non assegnato'
                            ) ?>" disabled>
                        </div>
                    </div>
                </div>

                <div class="detail-card">
                    <h3>Problema e diagnosi</h3>

                    <div class="form-group">
                        <label>Problema segnalato *</label>
                        <textarea name="reported_problem" rows="3" required><?= htmlspecialchars($form['reported_problem']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Codice errore</label>
                        <input type="text" name="error_code"
                            value="<?= htmlspecialchars($form['error_code'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Diagnosi</label>
                        <textarea name="diagnosis" rows="3"><?= htmlspecialchars($form['diagnosis'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Lavoro effettuato</label>
                        <textarea name="work_performed" rows="4"><?= htmlspecialchars($form['work_performed'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Ricambi sostituiti</label>
                        <textarea name="parts_replaced" rows="2"><?= htmlspecialchars($form['parts_replaced'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="detail-card">
                    <h3>Contatori e stato</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Contatore B/N</label>
                            <input type="number" min="0" name="counter_bw"
                                value="<?= htmlspecialchars((string)$form['counter_bw']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Contatore colore</label>
                            <input type="number" min="0" name="counter_color"
                                value="<?= htmlspecialchars((string)$form['counter_color']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Stato</label>
                            <select name="status">
                                <option value="pending" <?= $form['status'] === 'pending' ? 'selected' : '' ?>>In attesa</option>
                                <option value="in_progress" <?= $form['status'] === 'in_progress' ? 'selected' : '' ?>>In corso</option>
                                <option value="completed" <?= $form['status'] === 'completed' ? 'selected' : '' ?>>Completata</option>
                                <option value="waiting_parts" <?= $form['status'] === 'waiting_parts' ? 'selected' : '' ?>>In attesa ricambi</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Durata (minuti)</label>
                            <input type="number" min="0" name="duration_minutes"
                                value="<?= htmlspecialchars((string)($form['duration_minutes'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="notes" rows="3"><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="view.php?id=<?= (int)$id ?>" class="btn-secondary">Annulla</a>
                    <button type="submit" class="btn-primary">Salva modifiche</button>
                </div>

            </form>
        </section>
    </main>
</div>

<script>
const clientSelect = document.getElementById('client_id');
const printerSelect = document.getElementById('printer_id');
const originalPrinterId = <?= json_encode((string)$form['printer_id']) ?>;

async function loadPrinters(clientId, selectedId = '') {
    printerSelect.innerHTML = '<option value="">Caricamento...</option>';
    printerSelect.disabled = true;

    if (!clientId) {
        printerSelect.innerHTML = '<option value="">Seleziona prima un cliente</option>';
        return;
    }

    try {
        const response = await fetch(
            'edit.php?id=<?= (int)$id ?>&ajax=printers&client_id=' +
            encodeURIComponent(clientId)
        );

        if (!response.ok) throw new Error();

        const printers = await response.json();
        printerSelect.innerHTML = '';

        if (!printers.length) {
            printerSelect.innerHTML = '<option value="">Nessuna stampante associata</option>';
            return;
        }

        printerSelect.add(new Option('Seleziona stampante', ''));

        printers.forEach(printer => {
            const label = printer.brand + ' ' + printer.model +
                (printer.serial_number ? ' — ' + printer.serial_number : '');

            const option = new Option(label, printer.id);
            printerSelect.add(option);
        });

        printerSelect.disabled = false;
        printerSelect.value = String(selectedId);

    } catch (error) {
        printerSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
    }
}

clientSelect.addEventListener('change', function() {
    loadPrinters(this.value);
});

loadPrinters(clientSelect.value, originalPrinterId);
</script>

</body>
</html>
