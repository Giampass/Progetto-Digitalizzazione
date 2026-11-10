
<?php

require_once '../../config/database.php';
require_once '../../includes/technician.php';

$technicianId = requireTechnician();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Recupera la stampante
$stmt = $pdo->prepare("SELECT * FROM printers WHERE id = ?");
$stmt->execute([$id]);
$printer = $stmt->fetch();

if (!$printer) {
    http_response_code(404);
    die('Stampante non trovata.');
}

// Recupera i clienti
$clients = $pdo->query("
    SELECT id, company_name
    FROM clients
    ORDER BY company_name ASC
")->fetchAll();

$errors = [];

// Token di sicurezza del modulo
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Richiesta non valida. Ricarica la pagina.');
    }

    $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $ipAddress = trim($_POST['ip_address'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $counterBwRaw = $_POST['counter_bw'] ?? '';
    $counterColorRaw = $_POST['counter_color'] ?? '';

    if (!$clientId) {
        $errors[] = 'Seleziona un cliente.';
    }

    if ($brand === '') {
        $errors[] = 'Inserisci la marca.';
    }

    if ($model === '') {
        $errors[] = 'Inserisci il modello.';
    }

    if ($counterBwRaw === '' || !ctype_digit((string)$counterBwRaw)) {
        $errors[] = 'Inserisci un contatore B/N valido.';
    }

    if ($counterColorRaw === '' || !ctype_digit((string)$counterColorRaw)) {
        $errors[] = 'Inserisci un contatore colore valido.';
    }

    $counterBw = ctype_digit((string)$counterBwRaw)
        ? (int)$counterBwRaw : 0;

    $counterColor = ctype_digit((string)$counterColorRaw)
        ? (int)$counterColorRaw : 0;

    // Verifica cliente
    if ($clientId) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);

        if (!$stmt->fetch()) {
            $errors[] = 'Il cliente selezionato non esiste.';
        }
    }

    if (empty($errors)) {

        try {
            $stmt = $pdo->prepare("
                UPDATE printers SET
                    client_id = ?,
                    brand = ?,
                    model = ?,
                    serial_number = ?,
                    ip_address = ?,
                    location = ?,
                    counter_bw = ?,
                    counter_color = ?,
                    notes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $clientId,
                $brand,
                $model,
                $serialNumber ?: null,
                $ipAddress ?: null,
                $location ?: null,
                $counterBw,
                $counterColor,
                $notes ?: null,
                $id
            ]);

            logActivity(
                $pdo,
                'UPDATE',
                'printer',
                $id,
                "Stampante aggiornata: $brand $model"
            );

            header("Location: view.php?id=" . $id);
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Errore durante la modifica della stampante.';
        }
    }

    // Mantiene i valori inseriti in caso di errore
    $printer = array_merge($printer, [
        'client_id' => $clientId,
        'brand' => $brand,
        'model' => $model,
        'serial_number' => $serialNumber,
        'ip_address' => $ipAddress,
        'location' => $location,
        'counter_bw' => $counterBwRaw,
        'counter_color' => $counterColorRaw,
        'notes' => $notes
    ]);
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica stampante - Digitalizzazione</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Modifica stampante</h1>
                <p><?= htmlspecialchars($printer['brand'] . ' ' . $printer['model']) ?></p>
            </div>

             

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="section-header">
                <h2>Dati della stampante</h2>

                <a href="view.php?id=<?= $id ?>" class="btn-secondary">
                    ← Torna alla scheda
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="form-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="client-form">

                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="client_id" required>
                        <option value="">Seleziona cliente</option>

                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"
                                <?= (int)$printer['client_id'] === (int)$client['id']
                                    ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Marca *</label>
                        <input type="text" name="brand" required
                               value="<?= htmlspecialchars($printer['brand']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Modello *</label>
                        <input type="text" name="model" required
                               value="<?= htmlspecialchars($printer['model']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Numero seriale</label>
                        <input type="text" name="serial_number"
                               value="<?= htmlspecialchars($printer['serial_number'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Indirizzo IP</label>
                        <input type="text" name="ip_address"
                               value="<?= htmlspecialchars($printer['ip_address'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ubicazione</label>
                    <input type="text" name="location"
                           value="<?= htmlspecialchars($printer['location'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contatore bianco e nero</label>
                        <input type="number" name="counter_bw" min="0" required
                               value="<?= htmlspecialchars((string)$printer['counter_bw']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Contatore colore</label>
                        <input type="number" name="counter_color" min="0" required
                               value="<?= htmlspecialchars((string)$printer['counter_color']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Note tecniche</label>
                    <textarea name="notes" rows="4"><?= htmlspecialchars($printer['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="view.php?id=<?= $id ?>" class="btn-secondary">
                        Annulla
                    </a>

                    <button type="submit" class="btn-primary">
                        Salva modifiche
                    </button>
                </div>

            </form>

        </section>
    </main>
</div>

</body>
</html>
