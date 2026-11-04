
<?php

require_once '../../config/database.php';
require_once '../../includes/technician.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Recupera i dati della stampante e del cliente
$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.company_name,
        c.contact_person,
        c.phone,
        c.email,
        c.address,
        c.city,
        c.province
    FROM printers p
    INNER JOIN clients c ON p.client_id = c.id
    WHERE p.id = ?
");

$stmt->execute([$id]);
$printer = $stmt->fetch();

if (!$printer) {
    http_response_code(404);
    die('Stampante non trovata.');
}

// Recupera lo storico delle assistenze
$stmt = $pdo->prepare("
    SELECT
        a.*,
        t.name AS technician_name
    FROM assistances a
    LEFT JOIN technicians t ON a.technician_id = t.id
    WHERE a.printer_id = ?
    ORDER BY a.assistance_date DESC, a.id DESC
");

$stmt->execute([$id]);
$assistances = $stmt->fetchAll();

// Recupera il registro delle modifiche
$stmt = $pdo->prepare("
    SELECT
        l.*,
        t.name AS technician_name
    FROM activity_logs l
    LEFT JOIN technicians t ON l.technician_id = t.id
    WHERE l.entity_type = 'printer'
    AND l.entity_id = ?
    ORDER BY l.created_at DESC
");

$stmt->execute([$id]);
$logs = $stmt->fetchAll();

function printerStatusLabel($status) {
    $statuses = [
        'pending' => 'In attesa',
        'in_progress' => 'In corso',
        'completed' => 'Completata',
        'waiting_parts' => 'In attesa di ricambi'
    ];

    return $statuses[$status] ?? $status;
}

?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio stampante - Digitalizzazione</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1><?= htmlspecialchars($printer['brand'] . ' ' . $printer['model']) ?></h1>
                <p>Scheda tecnica della stampante</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date">
                <?= date('d/m/Y') ?>
            </div>
        </header>

        <section class="content">

            <div class="section-header">
                <div>
                    <h2>Dettagli macchina</h2>
                    <p>
                        Seriale:
                        <?= htmlspecialchars($printer['serial_number'] ?: 'Non disponibile') ?>
                    </p>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-secondary">
                        ← Torna alle stampanti
                    </a>

                    <a href="edit.php?id=<?= $id ?>" class="btn-primary">
                        Modifica stampante
                    </a>

                    <a href="delete.php?id=<?= $id ?>" class="btn-danger">
                        Elimina stampante
                    </a>
                </div>
            </div>

            <!-- DATI CLIENTE -->

            <div class="detail-card">
                <h3>Cliente associato</h3>

                <div class="detail-grid">

                    <div>
                        <span>Ragione sociale</span>
                        <strong>
                            <?= htmlspecialchars($printer['company_name']) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Persona di riferimento</span>
                        <strong>
                            <?= htmlspecialchars($printer['contact_person'] ?: '—') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Telefono</span>
                        <strong>
                            <?= htmlspecialchars($printer['phone'] ?: '—') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Email</span>
                        <strong>
                            <?= htmlspecialchars($printer['email'] ?: '—') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Indirizzo</span>
                        <strong>
                            <?= htmlspecialchars($printer['address'] ?: '—') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Città</span>
                        <strong>
                            <?= htmlspecialchars(trim(($printer['city'] ?? '') . ' ' . ($printer['province'] ?? ''))) ?: '—' ?>
                        </strong>
                    </div>

                </div>
            </div>

            <!-- DATI TECNICI -->

            <div class="detail-card">
                <h3>Informazioni tecniche</h3>

                <div class="detail-grid">

                    <div>
                        <span>Marca</span>
                        <strong><?= htmlspecialchars($printer['brand']) ?></strong>
                    </div>

                    <div>
                        <span>Modello</span>
                        <strong><?= htmlspecialchars($printer['model']) ?></strong>
                    </div>

                    <div>
                        <span>Numero seriale</span>
                        <strong><?= htmlspecialchars($printer['serial_number'] ?: '—') ?></strong>
                    </div>

                    <div>
                        <span>Indirizzo IP</span>
                        <strong><?= htmlspecialchars($printer['ip_address'] ?: '—') ?></strong>
                    </div>

                    <div>
                        <span>Ubicazione</span>
                        <strong><?= htmlspecialchars($printer['location'] ?: '—') ?></strong>
                    </div>

                    <div>
                        <span>Registrata il</span>
                        <strong><?= date('d/m/Y H:i', strtotime($printer['created_at'])) ?></strong>
                    </div>

                    <div>
                        <span>Ultima modifica</span>
                        <strong>
                            <?= !empty($printer['updated_at'])
                                ? date('d/m/Y H:i', strtotime($printer['updated_at']))
                                : '—' ?>
                        </strong>
                    </div>

                </div>
            </div>

            <!-- CONTATORI -->

            <div class="detail-card">
                <h3>Contatori macchina</h3>

                <div class="counter-grid">

                    <div class="counter-card">
                        <span>Bianco e nero</span>
                        <strong>
                            <?= number_format((int)$printer['counter_bw'], 0, ',', '.') ?>
                        </strong>
                    </div>

                    <div class="counter-card">
                        <span>Colore</span>
                        <strong>
                            <?= number_format((int)$printer['counter_color'], 0, ',', '.') ?>
                        </strong>
                    </div>

                </div>
            </div>

            <!-- NOTE -->

            <div class="detail-card">
                <h3>Note tecniche</h3>

                <p class="notes-content">
                    <?= nl2br(htmlspecialchars($printer['notes'] ?: 'Nessuna nota disponibile.')) ?>
                </p>
            </div>

            <!-- STORICO ASSISTENZE -->

            <div class="detail-card">
                <h3>Storico interventi</h3>

                <?php if (count($assistances) > 0): ?>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tecnico</th>
                                    <th>Problema segnalato</th>
                                    <th>Codice errore</th>
                                    <th>Stato</th>
                                    <th>Durata</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($assistances as $assistance): ?>

                                <tr>
                                    <td>
                                        <?= date('d/m/Y', strtotime($assistance['assistance_date'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($assistance['technician_name'] ?? '—') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($assistance['reported_problem']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($assistance['error_code'] ?: '—') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(printerStatusLabel($assistance['status'])) ?>
                                    </td>

                                    <td>
                                        <?= $assistance['duration_minutes'] !== null
                                            ? (int)$assistance['duration_minutes'] . ' min'
                                            : '—' ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                <?php else: ?>

                    <p class="empty-message">
                        Nessun intervento registrato per questa stampante.
                    </p>

                <?php endif; ?>
            </div>

            <!-- REGISTRO ATTIVITÀ -->

            <div class="detail-card">
                <h3>Registro modifiche</h3>

                <?php if (count($logs) > 0): ?>

                    <?php foreach ($logs as $log): ?>

                        <div class="activity-item">
                            <div>
                                <strong>
                                    <?= htmlspecialchars($log['description']) ?>
                                </strong>

                                <p>
                                    <?= htmlspecialchars($log['technician_name'] ?? 'Tecnico non disponibile') ?>
                                    ·
                                    <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </p>
                            </div>
                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty-message">
                        Nessuna modifica registrata.
                    </p>

                <?php endif; ?>
            </div>

        </section>

    </main>

</div>

</body>
</html>
