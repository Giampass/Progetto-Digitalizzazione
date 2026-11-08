
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$allowedStatuses = [
    'pending',
    'in_progress',
    'completed',
    'waiting_parts'
];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        c.company_name LIKE ?
        OR p.brand LIKE ?
        OR p.model LIKE ?
        OR p.serial_number LIKE ?
        OR a.error_code LIKE ?
        OR a.reported_problem LIKE ?
    )";

    $term = "%$search%";

    for ($i = 0; $i < 6; $i++) {
        $params[] = $term;
    }
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

$sql = "
    SELECT
        a.id,
        a.assistance_date,
        a.reported_problem,
        a.error_code,
        a.status,
        c.company_name,
        p.brand,
        p.model,
        p.serial_number,
        t.name AS technician_name
    FROM assistances a
    INNER JOIN clients c ON c.id = a.client_id
    INNER JOIN printers p ON p.id = a.printer_id
    LEFT JOIN technicians t ON t.id = a.technician_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY a.assistance_date DESC, a.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assistances = $stmt->fetchAll();

function assistanceStatusLabel($status) {
    $labels = [
        'pending' => 'In attesa',
        'in_progress' => 'In corso',
        'completed' => 'Completata',
        'waiting_parts' => 'In attesa ricambi'
    ];

    return $labels[$status] ?? 'Non definito';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistenze | TechSupport</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Assistenze</h1>
                <p>Gestione degli interventi tecnici</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date">
                <?= date('d/m/Y') ?>
            </div>
        </header>

        <section class="content">

            <?php if (isset($_GET['created'])): ?>
                <div class="success-message">
                    Intervento registrato correttamente.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="success-message">
                    Intervento eliminato correttamente.
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <h2>Elenco interventi</h2>
                    <p>
                        <?= count($assistances) ?>
                        interventi trovati
                    </p>
                </div>

                <a href="create.php" class="btn-primary">
                    + Nuova assistenza
                </a>
            </div>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Cerca cliente, stampante, matricola o problema..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <select name="status">
                    <option value="">Tutti gli stati</option>

                    <option value="pending"
                        <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                        In attesa
                    </option>

                    <option value="in_progress"
                        <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>
                        In corso
                    </option>

                    <option value="completed"
                        <?= $statusFilter === 'completed' ? 'selected' : '' ?>>
                        Completata
                    </option>

                    <option value="waiting_parts"
                        <?= $statusFilter === 'waiting_parts' ? 'selected' : '' ?>>
                        In attesa ricambi
                    </option>
                </select>

                <button type="submit" class="btn-primary">
                    Cerca
                </button>

                <a href="index.php" class="btn-secondary">
                    Azzera
                </a>

            </form>

            <div class="table-container">

                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Stampante</th>
                            <th>Problema segnalato</th>
                            <th>Codice errore</th>
                            <th>Tecnico</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($assistances)): ?>

                        <tr>
                            <td colspan="8" class="empty-message">
                                Nessun intervento trovato.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($assistances as $assistance): ?>

                            <tr>
                                <td>
                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime($assistance['assistance_date'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($assistance['company_name']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $assistance['brand'] . ' ' . $assistance['model']
                                        ) ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?= htmlspecialchars(
                                            $assistance['serial_number'] ?: 'Matricola non inserita'
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        mb_strimwidth($assistance['reported_problem'], 0, 65, '...')
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($assistance['error_code'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($assistance['technician_name'] ?: 'Non assegnato') ?>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($assistance['status']) ?>">
                                        <?= htmlspecialchars(
                                            assistanceStatusLabel($assistance['status'])
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="view.php?id=<?= (int)$assistance['id'] ?>"
                                        class="btn-secondary"
                                    >
                                        Dettagli
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>
