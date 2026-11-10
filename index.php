
<?php
require_once 'config/database.php';
require_once 'includes/technician.php';

$clients = $pdo->query(
    "SELECT COUNT(*) FROM clients"
)->fetchColumn();

$printers = $pdo->query(
    "SELECT COUNT(*) FROM printers"
)->fetchColumn();

$assistance = $pdo->query(
    "SELECT COUNT(*) FROM assistances"
)->fetchColumn();

$pending = $pdo->query(
    "SELECT COUNT(*) FROM assistances
     WHERE status IN ('pending', 'in_progress', 'waiting_parts')"
)->fetchColumn();

$recent = $pdo->query("
    SELECT a.*, c.company_name, p.brand, p.model
    FROM assistances a
    JOIN clients c ON c.id = a.client_id
    JOIN printers p ON p.id = a.printer_id
    ORDER BY a.assistance_date DESC
    LIMIT 8
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SELCOCOPY - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="app">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Dashboard</h1>
                <p>Panoramica delle attività tecniche</p>
            </div>

            <div class="date">
                <?= date('d/m/Y') ?>
            </div>
        </header>

        <section class="stats">

            <div class="stat-card">
                <span>Clienti</span>
                <h2><?= $clients ?></h2>
            </div>

            <div class="stat-card">
                <span>Stampanti</span>
                <h2><?= $printers ?></h2>
            </div>

            <div class="stat-card">
                <span>Assistenze totali</span>
                <h2><?= $assistance ?></h2>
            </div>

            <div class="stat-card warning">
                <span>Assistenze aperte</span>
                <h2><?= $pending ?></h2>
            </div>

        </section>

        <section class="panel">

            <div class="panel-header">
                <h2>Ultime assistenze</h2>
                <a href="modules/assistenze/">Vedi tutte</a>
            </div>

            <div class="table-container">

                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Stampante</th>
                            <th>Problema</th>
                            <th>Stato</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($recent as $item): ?>

                        <tr>
                            <td>
                                <?= date('d/m/Y', strtotime($item['assistance_date'])) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['company_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['reported_problem']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['status']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (empty($recent)): ?>
                        <tr>
                            <td colspan="5">
                                Nessuna assistenza registrata.
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>
