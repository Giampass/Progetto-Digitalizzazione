
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        a.*,
        c.company_name,
        c.phone,
        c.email,
        p.brand,
        p.model,
        p.serial_number,
        p.ip_address,
        p.location,
        t.name AS technician_name
    FROM assistances a
    INNER JOIN clients c ON c.id = a.client_id
    INNER JOIN printers p ON p.id = a.printer_id
    LEFT JOIN technicians t ON t.id = a.technician_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$assistance = $stmt->fetch();

if (!$assistance) {
    http_response_code(404);
    die('Intervento non trovato.');
}

$stmt = $pdo->prepare("
    SELECT l.*, t.name AS technician_name
    FROM activity_logs l
    LEFT JOIN technicians t ON t.id = l.technician_id
    WHERE l.entity_type = 'assistance'
    AND l.entity_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$id]);
$logs = $stmt->fetchAll();

$labels = [
    'pending' => 'In attesa',
    'in_progress' => 'In corso',
    'completed' => 'Completata',
    'waiting_parts' => 'In attesa ricambi'
];

$statusLabel = $labels[$assistance['status']] ?? 'Non definito';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio assistenza | TechSupport</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Dettaglio assistenza #<?= (int)$id ?></h1>
                <p>Informazioni complete dell'intervento</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">
            <?php if (isset($_GET['updated'])): ?>
                <div class="success-message">
                    Intervento aggiornato correttamente.
                </div>
            <?php endif; ?>

            <div class="detail-card">
                <h3>Informazioni generali</h3>

                <div class="detail-grid">
                    <div>
                        <span>Data intervento</span>
                        <strong><?= date('d/m/Y H:i', strtotime($assistance['assistance_date'])) ?></strong>
                    </div>

                    <div>
                        <span>Cliente</span>
                        <strong><?= htmlspecialchars($assistance['company_name']) ?></strong>
                    </div>

                    <div>
                        <span>Tecnico</span>
                        <strong><?= htmlspecialchars($assistance['technician_name'] ?: 'Non assegnato') ?></strong>
                    </div>

                    <div>
                        <span>Stampante</span>
                        <strong><?= htmlspecialchars($assistance['brand'] . ' ' . $assistance['model']) ?></strong>
                    </div>

                    <div>
                        <span>Matricola</span>
                        <strong><?= htmlspecialchars($assistance['serial_number'] ?: '—') ?></strong>
                    </div>

                    <div>
                        <span>Codice errore</span>
                        <strong><?= htmlspecialchars($assistance['error_code'] ?: '—') ?></strong>
                    </div>

                    <div>
                        <span>Stato</span>
                        <strong>
                            <span class="status-badge status-<?= htmlspecialchars($assistance['status']) ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </strong>
                    </div>

                    <div>
                        <span>Durata</span>
                        <strong>
                            <?= $assistance['duration_minutes'] !== null
                                ? (int)$assistance['duration_minutes'] . ' minuti'
                                : '—' ?>
                        </strong>
                    </div>

                    <div>
                        <span>IP stampante</span>
                        <strong><?= htmlspecialchars($assistance['ip_address'] ?: '—') ?></strong>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <h3>Problema segnalato</h3>
                <div class="notes-content">
                    <?= nl2br(htmlspecialchars($assistance['reported_problem'])) ?>
                </div>
            </div>

            <div class="detail-card">
                <h3>Diagnosi tecnica</h3>
                <div class="notes-content">
                    <?= $assistance['diagnosis']
                        ? nl2br(htmlspecialchars($assistance['diagnosis']))
                        : 'Nessuna diagnosi inserita.' ?>
                </div>
            </div>

            <div class="detail-card">
                <h3>Lavoro effettuato</h3>
                <div class="notes-content">
                    <?= $assistance['work_performed']
                        ? nl2br(htmlspecialchars($assistance['work_performed']))
                        : 'Nessun lavoro registrato.' ?>
                </div>
            </div>

            <div class="detail-card">
                <h3>Ricambi sostituiti</h3>
                <div class="notes-content">
                    <?= $assistance['parts_replaced']
                        ? nl2br(htmlspecialchars($assistance['parts_replaced']))
                        : 'Nessun ricambio registrato.' ?>
                </div>
            </div>

            <div class="detail-card">
                <h3>Contatori rilevati</h3>

                <div class="counter-grid">
                    <div class="counter-card">
                        <span>Contatore B/N</span>
                        <strong><?= number_format((int)$assistance['counter_bw'], 0, ',', '.') ?></strong>
                    </div>

                    <div class="counter-card">
                        <span>Contatore colore</span>
                        <strong><?= number_format((int)$assistance['counter_color'], 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>

            <?php if ($assistance['notes']): ?>
                <div class="detail-card">
                    <h3>Note aggiuntive</h3>
                    <div class="notes-content">
                        <?= nl2br(htmlspecialchars($assistance['notes'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="detail-card">
                <h3>Registro attività</h3>

                <?php if (empty($logs)): ?>
                    <p class="empty-message">Nessuna attività registrata.</p>
                <?php else: ?>

                    <?php foreach ($logs as $log): ?>
                        <div class="activity-item">
                            <strong>
                                <?= htmlspecialchars($log['action']) ?>
                                — <?= htmlspecialchars($log['technician_name'] ?: 'Sistema') ?>
                            </strong>
                            <p><?= htmlspecialchars($log['description']) ?></p>
                            <p><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-secondary">← Torna alle assistenze</a>
                <a href="edit.php?id=<?= (int)$id ?>" class="btn-primary">Modifica intervento</a>
                <a href="delete.php?id=<?= (int)$id ?>" class="btn-danger">Elimina intervento</a>
            </div>

        </section>
    </main>
</div>

</body>
</html>
