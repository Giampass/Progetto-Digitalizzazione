
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$search = trim($_GET['search'] ?? '');
$actionFilter = $_GET['action'] ?? '';
$entityFilter = $_GET['entity'] ?? '';

$allowedActions = ['CREATE', 'UPDATE', 'DELETE'];
$allowedEntities = ['client', 'printer', 'assistance'];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        l.description LIKE ?
        OR t.name LIKE ?
        OR CAST(l.entity_id AS CHAR) LIKE ?
    )";

    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}

if (in_array($actionFilter, $allowedActions, true)) {
    $where[] = "l.action = ?";
    $params[] = $actionFilter;
}

if (in_array($entityFilter, $allowedEntities, true)) {
    $where[] = "l.entity_type = ?";
    $params[] = $entityFilter;
}

$sql = "
    SELECT
        l.id,
        l.technician_id,
        l.action,
        l.entity_type,
        l.entity_id,
        l.description,
        l.created_at,
        t.name AS technician_name,

        CASE
            WHEN l.entity_type = 'client' AND c.id IS NOT NULL THEN 1
            WHEN l.entity_type = 'printer' AND p.id IS NOT NULL THEN 1
            WHEN l.entity_type = 'assistance' AND a.id IS NOT NULL THEN 1
            ELSE 0
        END AS entity_exists

    FROM activity_logs l

    LEFT JOIN technicians t
        ON t.id = l.technician_id

    LEFT JOIN clients c
        ON l.entity_type = 'client'
        AND c.id = l.entity_id

    LEFT JOIN printers p
        ON l.entity_type = 'printer'
        AND p.id = l.entity_id

    LEFT JOIN assistances a
        ON l.entity_type = 'assistance'
        AND a.id = l.entity_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY l.created_at DESC, l.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

function historyActionLabel($action) {
    $labels = [
        'CREATE' => 'Creazione',
        'UPDATE' => 'Modifica',
        'DELETE' => 'Eliminazione'
    ];

    return $labels[$action] ?? $action;
}

function historyEntityLabel($entity) {
    $labels = [
        'client' => 'Cliente',
        'printer' => 'Stampante',
        'assistance' => 'Intervento'
    ];

    return $labels[$entity] ?? $entity;
}

function historyEntityLink($entity, $id) {
    $id = (int)$id;

    switch ($entity) {
        case 'client':
            return "../clienti/view.php?id=" . $id;

        case 'printer':
            return "../stampanti/view.php?id=" . $id;

        case 'assistance':
            return "../assistenze/view.php?id=" . $id;

        default:
            return null;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storico | SELCOCOPY</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Storico attività</h1>
                <p>Registro delle operazioni effettuate dai tecnici</p>
            </div>

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="section-header">
                <div>
                    <h2>Registro attività</h2>
                    <p><?= count($logs) ?> attività trovate</p>
                </div>
            </div>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Cerca descrizione, tecnico o ID..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <select name="action">
                    <option value="">Tutte le operazioni</option>

                    <option value="CREATE"
                        <?= $actionFilter === 'CREATE' ? 'selected' : '' ?>>
                        Creazioni
                    </option>

                    <option value="UPDATE"
                        <?= $actionFilter === 'UPDATE' ? 'selected' : '' ?>>
                        Modifiche
                    </option>

                    <option value="DELETE"
                        <?= $actionFilter === 'DELETE' ? 'selected' : '' ?>>
                        Eliminazioni
                    </option>
                </select>

                <select name="entity">
                    <option value="">Tutti gli elementi</option>

                    <option value="client"
                        <?= $entityFilter === 'client' ? 'selected' : '' ?>>
                        Clienti
                    </option>

                    <option value="printer"
                        <?= $entityFilter === 'printer' ? 'selected' : '' ?>>
                        Stampanti
                    </option>

                    <option value="assistance"
                        <?= $entityFilter === 'assistance' ? 'selected' : '' ?>>
                        Interventi
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
                            <th>Data e ora</th>
                            <th>Tecnico</th>
                            <th>Operazione</th>
                            <th>Elemento</th>
                            <th>ID</th>
                            <th>Descrizione</th>
                            <th>Dettagli</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($logs) > 0): ?>

                        <?php foreach ($logs as $log): ?>

                            <?php
                            $link = historyEntityLink(
                                $log['entity_type'],
                                $log['entity_id']
                            );
                            ?>

                            <tr>
                                <td>
                                    <?= date(
                                        'd/m/Y H:i:s',
                                        strtotime($log['created_at'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $log['technician_name'] ?: 'Sistema'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="history-action action-<?= strtolower($log['action']) ?>">
                                        <?= htmlspecialchars(historyActionLabel($log['action'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(historyEntityLabel($log['entity_type'])) ?>
                                </td>

                                <td>
                                    #<?= (int)$log['entity_id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['description']) ?>
                                </td>

                                <td>

                                    <?php if ($log['action'] === 'DELETE'): ?>

                                        <span class="empty-message">Elemento eliminato</span>

                                    <?php elseif ($link && $log['entity_exists']): ?>

                                        <a href="<?= htmlspecialchars($link) ?>"
                                        class="btn-secondary">
                                            Apri
                                        </a>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn-secondary"
                                            onclick="showMissingElement(
                                                '<?= htmlspecialchars(
                                                    historyEntityLabel($log['entity_type']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>'
                                            )">
                                            Non disponibile
                                        </button>

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div class="empty-state-content">
                                    <span class="empty-state-icon">🔎</span>
                                    <strong>Nessuna attività trovata</strong>
                                    <p>
                                            Non sono state trovate attività corrispondenti
                                            ai criteri di ricerca.
                                    </p>
                                    <a href="index.php" class="btn-secondary">
                                            Mostra tutte le attività
                                    </a>
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
<script>
function showMissingElement(type) {

    let message = '';

    switch (type) {
        case 'Cliente':
            message = 'Il cliente associato a questa attività non è più presente nel sistema.';
            break;

        case 'Stampante':
            message = 'La stampante associata a questa attività non è più presente nel sistema.';
            break;

        case 'Intervento':
            message = 'Questo intervento non è più presente nel sistema.';
            break;

        default:
            message = 'L’elemento richiesto non è disponibile.';
    }

    alert(
        'ELEMENTO NON DISPONIBILE\n\n' +
        message +
        '\n\nIl registro storico è stato conservato.'
    );
}
</script>
</body>
</html>
