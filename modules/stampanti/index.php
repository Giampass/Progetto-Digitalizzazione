
<?php

require_once '../../config/database.php';
require_once '../../includes/technician.php';

// Buscar impresoras
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        p.*,
        c.company_name
    FROM printers p
    INNER JOIN clients c ON p.client_id = c.id
";

$params = [];

if ($search !== '') {
    $sql .= "
        WHERE
            c.company_name LIKE ?
            OR p.brand LIKE ?
            OR p.model LIKE ?
            OR p.serial_number LIKE ?
            OR p.ip_address LIKE ?
    ";

    $term = "%{$search}%";
    $params = [$term, $term, $term, $term, $term];
}

$sql .= " ORDER BY c.company_name ASC, p.brand ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$printers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stampanti - Digitalizzazione</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Stampanti</h1>
                <p>Gestione delle stampanti dei clienti</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date">
                <?= date('d/m/Y') ?>
            </div>
        </header>

        <section class="content">

            <div class="section-header">
                <div>
                    <h2>Elenco stampanti</h2>
                    <p><?= count($printers) ?> stampanti registrate</p>
                </div>

                <a href="create.php" class="btn-primary">
                    + Nuova stampante
                </a>
            </div>

            <form method="GET" class="search-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Cerca cliente, marca, modello, seriale o IP..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button type="submit" class="btn-primary">
                    Cerca
                </button>

                <?php if ($search !== ''): ?>
                    <a href="index.php" class="btn-secondary">
                        Reset
                    </a>
                <?php endif; ?>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Marca</th>
                            <th>Modello</th>
                            <th>Numero seriale</th>
                            <th>Indirizzo IP</th>
                            <th>Contatore B/N</th>
                            <th>Contatore Colore</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($printers) > 0): ?>

                        <?php foreach ($printers as $printer): ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars($printer['company_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($printer['brand']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($printer['model']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($printer['serial_number'] ?? '') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($printer['ip_address'] ?? '') ?>
                                </td>

                                <td>
                                    <?= number_format((int)$printer['counter_bw'], 0, ',', '.') ?>
                                </td>

                                <td>
                                    <?= number_format((int)$printer['counter_color'], 0, ',', '.') ?>
                                </td>

                                <td>
                                    <a href="view.php?id=<?= $printer['id'] ?>">
                                        Visualizza
                                    </a>
                                    |
                                    <a href="edit.php?id=<?= $printer['id'] ?>">
                                        Modifica
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8">
                                Nessuna stampante trovata.
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
