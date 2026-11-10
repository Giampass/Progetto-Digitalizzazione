
<?php

require_once '../../config/database.php';
require_once '../../includes/technician.php';

$technicianId = requireTechnician();

// Recupera i clienti
$clients = $pdo->query("
    SELECT id, company_name
    FROM clients
    ORDER BY company_name ASC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $ipAddress = trim($_POST['ip_address'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $counterBw = filter_input(INPUT_POST, 'counter_bw', FILTER_VALIDATE_INT);
    $counterColor = filter_input(INPUT_POST, 'counter_color', FILTER_VALIDATE_INT);
    $notes = trim($_POST['notes'] ?? '');

    // Validazione
    if (!$clientId) {
        $errors[] = "Seleziona un cliente.";
    }

    if ($brand === '') {
        $errors[] = "Inserisci la marca.";
    }

    if ($model === '') {
        $errors[] = "Inserisci il modello.";
    }

    if ($counterBw === false || $counterBw === null) {
        $counterBw = 0;
    }

    if ($counterColor === false || $counterColor === null) {
        $counterColor = 0;
    }

    if ($counterBw < 0 || $counterColor < 0) {
        $errors[] = "I contatori non possono essere negativi.";
    }

    // Verifica che il cliente esista
    if ($clientId) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);

        if (!$stmt->fetch()) {
            $errors[] = "Il cliente selezionato non esiste.";
        }
    }

    if (empty($errors)) {

        try {
            $stmt = $pdo->prepare("
                INSERT INTO printers (
                    client_id,
                    brand,
                    model,
                    serial_number,
                    ip_address,
                    location,
                    counter_bw,
                    counter_color,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $notes ?: null
            ]);

            $printerId = (int) $pdo->lastInsertId();

            logActivity(
                $pdo,
                'CREATE',
                'printer',
                $printerId,
                "Nuova stampante registrata: $brand $model"
            );

            header("Location: view.php?id=" . $printerId);
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = "Errore durante il salvataggio della stampante.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova stampante - Digitalizzazione</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Nuova stampante</h1>
                <p>Registrazione di una nuova macchina</p>
            </div>

             

            <div class="date">
                <?= date('d/m/Y') ?>
            </div>
        </header>

        <section class="content">

            <div class="section-header">
                <h2>Dati della stampante</h2>

                <a href="index.php" class="btn-secondary">
                    ← Torna alle stampanti
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="form-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="client-form">

                <div class="form-group">
                    <label>Cliente *</label>

                    <select name="client_id" required>
                        <option value="">Seleziona cliente</option>

                        <?php foreach ($clients as $client): ?>
                            <option
                                value="<?= $client['id'] ?>"
                                <?= (isset($_POST['client_id']) &&
                                    $_POST['client_id'] == $client['id'])
                                    ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($client['company_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label>Marca *</label>
                        <input
                            type="text"
                            name="brand"
                            placeholder="Es. Sharp, Brother, Canon..."
                            value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Modello *</label>
                        <input
                            type="text"
                            name="model"
                            placeholder="Es. BP-50C26"
                            value="<?= htmlspecialchars($_POST['model'] ?? '') ?>"
                            required
                        >
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label>Numero seriale</label>
                        <input
                            type="text"
                            name="serial_number"
                            value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Indirizzo IP</label>
                        <input
                            type="text"
                            name="ip_address"
                            placeholder="Es. 192.168.1.100"
                            value="<?= htmlspecialchars($_POST['ip_address'] ?? '') ?>"
                        >
                    </div>

                </div>

                <div class="form-group">
                    <label>Ubicazione</label>
                    <input
                        type="text"
                        name="location"
                        placeholder="Es. Ufficio amministrazione"
                        value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                    >
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label>Contatore bianco e nero</label>
                        <input
                            type="number"
                            name="counter_bw"
                            min="0"
                            value="<?= htmlspecialchars($_POST['counter_bw'] ?? '0') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Contatore colore</label>
                        <input
                            type="number"
                            name="counter_color"
                            min="0"
                            value="<?= htmlspecialchars($_POST['counter_color'] ?? '0') ?>"
                        >
                    </div>

                </div>

                <div class="form-group">
                    <label>Note tecniche</label>
                    <textarea
                        name="notes"
                        rows="4"
                        placeholder="Informazioni aggiuntive sulla stampante..."
                    ><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-secondary">
                        Annulla
                    </a>

                    <button type="submit" class="btn-primary">
                        Salva stampante
                    </button>
                </div>

            </form>

        </section>

    </main>

</div>

</body>
</html>
