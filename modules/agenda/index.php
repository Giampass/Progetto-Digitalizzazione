
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

// Recupera tutte le liste attività
$stmt = $pdo->query("
    SELECT
        a.id,
        a.title,
        a.list_date,
        a.technician_name,
        t.name AS current_technician,
        a.status,
        a.created_at,

        (
            SELECT COUNT(*)
            FROM agenda_items i
            WHERE i.agenda_list_id = a.id
        ) AS total_tasks,

        (
            SELECT COUNT(*)
            FROM agenda_items i
            WHERE i.agenda_list_id = a.id
            AND i.status = 'completed'
        ) AS completed_tasks

    FROM agenda_lists a

    LEFT JOIN technicians t
        ON t.id = a.technician_id

    ORDER BY a.list_date DESC, a.id DESC
");

$lists = $stmt->fetchAll();

function agendaStatusLabel($status) {
    $labels = [
        'planned' => 'Pianificata',
        'completed' => 'Completata',
        'cancelled' => 'Annullata'
    ];

    return $labels[$status] ?? $status;
}

?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Agenda attività | SELCOCOPY</title>

    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Agenda attività</h1>
                <p>Organizza le visite e gli interventi dei tecnici</p>
            </div>

            <div class="date">
                <?= date('d/m/Y') ?>
            </div>

        </header>

        <section class="content">

            <div class="section-header">
                <div>
                    <h2>Le mie liste attività</h2>
                    <p><?= count($lists) ?> liste trovate</p>
                </div>

                <a href="create.php" class="btn-primary">
                    + Nuova lista
                </a>
            </div>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert-success">
                    Lista attività creata correttamente.
                </div>
            <?php endif; ?>

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome lista</th>
                            <th>Data</th>
                            <th>Tecnico</th>
                            <th>Attività</th>
                            <th>Completate</th>
                            <th>Stato</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($lists)): ?>

                        <?php foreach ($lists as $list): ?>

                            <?php
                                $technician = $list['current_technician']
                                    ?: $list['technician_name']
                                    ?: 'Non disponibile';
                            ?>

                            <tr>

                                <td>
                                    #<?= (int)$list['id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $list['title'] ?: 'Lista #' . $list['id']
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= date(
                                        'd/m/Y',
                                        strtotime($list['list_date'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($technician) ?>
                                </td>

                                <td>
                                    <?= (int)$list['total_tasks'] ?>
                                </td>

                                <td>
                                    <?= (int)$list['completed_tasks'] ?>
                                    /
                                    <?= (int)$list['total_tasks'] ?>
                                </td>

                                <td>
                                    <span class="agenda-status status-<?= htmlspecialchars($list['status']) ?>">
                                        <?= htmlspecialchars(
                                            agendaStatusLabel($list['status'])
                                        ) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty-state">

                                <div class="empty-state-content">

                                    <span class="empty-state-icon">📋</span>

                                    <strong>Nessuna lista attività</strong>

                                    <p>
                                        Non hai ancora creato nessuna lista.
                                        Le tue future attività appariranno qui.
                                    </p>

                                </div>

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
