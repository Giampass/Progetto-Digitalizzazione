
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    die('Cliente non trovato.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM activity_logs
    WHERE entity_type = 'client' AND entity_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$id]);
$logs = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT id, brand, model, serial_number, location
    FROM printers
    WHERE client_id = ?
    ORDER BY brand, model
");
$stmt->execute([$id]);
$printers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Scheda cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app">

<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

<header class="topbar">
    <div>
        <h1><?= htmlspecialchars($client['company_name']) ?></h1>
        <p>Scheda anagrafica cliente</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <h2>Dati cliente</h2>
        <div style="display:flex;gap:10px;">
    <a class="btn-primary" href="edit.php?id=<?= $id ?>">
        Modifica
    </a>

    <a class="btn-danger" href="delete.php?id=<?= $id ?>">
        Elimina
    </a>
</div>
    </div>

    <div class="client-details">
        <?php
        $details = [
            'Persona di riferimento' => 'contact_person',
            'Telefono' => 'phone',
            'Email' => 'email',
            'Partita IVA' => 'vat_number',
            'Indirizzo' => 'address',
            'Città' => 'city',
            'Provincia' => 'province'
        ];
        ?>

        <?php foreach ($details as $label => $field): ?>
            <div>
                <span><?= $label ?></span>
                <strong><?= htmlspecialchars($client[$field] ?? '-') ?: '-' ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="notes">
        <strong>Note:</strong><br>
        <?= nl2br(htmlspecialchars($client['notes'] ?? '-')) ?>
    </p>

    <p class="notes">
        <strong>Ultima modifica:</strong>
        <?= $client['updated_at']
            ? date('d/m/Y H:i', strtotime($client['updated_at']))
            : '-' ?>
    </p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Stampanti associate (<?= count($printers) ?>)</h2>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Marca</th>
                    <th>Modello</th>
                    <th>Numero seriale</th>
                    <th>Ubicazione</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($printers as $printer): ?>
                <tr>
                    <td><?= htmlspecialchars($printer['brand']) ?></td>
                    <td><?= htmlspecialchars($printer['model']) ?></td>
                    <td><?= htmlspecialchars($printer['serial_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($printer['location'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$printers): ?>
                <tr><td colspan="4">Nessuna stampante associata.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Storico modifiche</h2>

    <?php if (!$logs): ?>
        <p class="notes">Nessuna attività registrata.</p>
    <?php endif; ?>

    <?php foreach ($logs as $log): ?>
        <div class="activity-item">
            <strong><?= htmlspecialchars($log['action']) ?></strong>
            <p><?= htmlspecialchars($log['description']) ?></p>
            <small>
                <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                ·
                <?php
                $techStmt = $pdo->prepare(
                    "SELECT name FROM technicians WHERE id = ?"
                );
                $techStmt->execute([$log['technician_id']]);
                echo htmlspecialchars($techStmt->fetchColumn() ?: 'Tecnico non disponibile');
                ?>
            </small>
        </div>
    <?php endforeach; ?>
</section>

</main>
</div>
</body>
</html>
