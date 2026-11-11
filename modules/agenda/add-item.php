
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

// Recupera l'ID della lista
$listId = (int)($_GET['list_id'] ?? 0);

if ($listId <= 0) {
    die('Lista attività non valida.');
}

// Recupera i dati della lista
$stmt = $pdo->prepare("
    SELECT *
    FROM agenda_lists
    WHERE id = ?
");
$stmt->execute([$listId]);
$agendaList = $stmt->fetch();

if (!$agendaList) {
    die('Lista attività non trovata.');
}

// Recupera i clienti
$stmt = $pdo->query("
    SELECT id, company_name, address, city, province, phone
    FROM clients
    ORDER BY company_name ASC
");
$clients = $stmt->fetchAll();

// Recupera le stampanti
$stmt = $pdo->query("
    SELECT id, client_id, brand, model, serial_number
    FROM printers
    ORDER BY brand, model
");
$printers = $stmt->fetchAll();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aggiungi attività | SELCOCOPY</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Aggiungi attività</h1>
                <p><?= e($agendaList['title'] ?: 'Lista #' . $agendaList['id']) ?></p>
            </div>
            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="section-header">
                <div>
                    <h2>Nuova visita</h2>
                    <p>Inserisci il cliente e il lavoro da effettuare.</p>
                </div>
            </div>

            <div class="form-container">

                <form action="save-item.php" method="POST">

                    <input type="hidden" name="agenda_list_id"
                           value="<?= (int)$agendaList['id'] ?>">

                    <div class="form-group">
                        <label for="client_id">Cliente *</label>

                        <select name="client_id" id="client_id" required>
                            <option value="">Seleziona un cliente</option>

                            <?php foreach ($clients as $client): ?>
                                <option
                                    value="<?= (int)$client['id'] ?>"
                                    data-address="<?= e($client['address']) ?>"
                                    data-city="<?= e($client['city']) ?>"
                                    data-province="<?= e($client['province']) ?>"
                                    data-phone="<?= e($client['phone']) ?>"
                                >
                                    <?= e($client['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="printer_id">Stampante</label>

                        <select name="printer_id" id="printer_id" disabled>
                            <option value="">Nessuna stampante selezionata</option>

                            <?php foreach ($printers as $printer): ?>
                                <option
                                    value="<?= (int)$printer['id'] ?>"
                                    data-client="<?= (int)$printer['client_id'] ?>"
                                >
                                    <?= e($printer['brand'] . ' ' .
                                        $printer['model'] . ' - ' .
                                        $printer['serial_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small>Seleziona prima un cliente.</small>
                    </div>

                    <h3>Dati del cliente</h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Indirizzo</label>
                            <input type="text" id="client_address" readonly>
                        </div>

                        <div class="form-group">
                            <label>Città</label>
                            <input type="text" id="client_city" readonly>
                        </div>

                        <div class="form-group">
                            <label>Provincia</label>
                            <input type="text" id="client_province" readonly>
                        </div>

                        <div class="form-group">
                            <label>Telefono</label>
                            <input type="text" id="client_phone" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="task_description">Descrizione attività *</label>
                        <textarea
                            name="task_description"
                            id="task_description"
                            rows="5"
                            required
                            placeholder="Descrivi il lavoro da effettuare..."
                        ></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="view.php?id=<?= (int)$agendaList['id'] ?>"
                           class="btn btn-secondary">
                            Annulla
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Salva attività
                        </button>
                    </div>

                </form>

            </div>

        </section>
    </main>
</div>

<script>
const clientSelect = document.getElementById('client_id');
const printerSelect = document.getElementById('printer_id');

clientSelect.addEventListener('change', function () {

    const selected = this.options[this.selectedIndex];

    document.getElementById('client_address').value =
        selected.dataset.address || '';

    document.getElementById('client_city').value =
        selected.dataset.city || '';

    document.getElementById('client_province').value =
        selected.dataset.province || '';

    document.getElementById('client_phone').value =
        selected.dataset.phone || '';

    const clientId = this.value;

    printerSelect.value = '';
    printerSelect.disabled = !clientId;

    Array.from(printerSelect.options).forEach(option => {

        if (option.value === '') {
            option.hidden = false;
            return;
        }

        option.hidden = option.dataset.client !== clientId;
    });
});
</script>

</body>
</html>
