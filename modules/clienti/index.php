
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM clients";
$params = [];

if ($search !== '') {
    $sql .= " WHERE company_name LIKE ?
              OR phone LIKE ?
              OR vat_number LIKE ?
              OR contact_person LIKE ?";

    $term = "%$search%";
    $params = [$term, $term, $term, $term];
}

$sql .= " ORDER BY company_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clienti - TechSupport</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app">

<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

    <header class="topbar">
        <div>
            <h1>Clienti</h1>
            <p>Gestione anagrafica clienti</p>
        </div>

        <?php include '../../includes/technician-selector.php'; ?>
    </header>

    <section class="panel">

        <div class="panel-header">
            <h2>Elenco clienti (<?= count($clients) ?>)</h2>

            <a class="btn-primary" href="create.php">
                + Nuovo cliente
            </a>
        </div>

        <form method="GET" class="search-form">
            <input
                type="text"
                name="search"
                placeholder="Cerca cliente, telefono o P.IVA..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit">Cerca</button>

            <a href="index.php">Reset</a>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ragione sociale</th>
                        <th>Contatto</th>
                        <th>Telefono</th>
                        <th>Città</th>
                        <th>Ultima modifica</th>
                        <th>Azioni</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= htmlspecialchars($client['company_name']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($client['contact_person'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($client['phone'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($client['city'] ?? '') ?>
                        </td>

                        <td>
                            <?= $client['updated_at']
                                ? date('d/m/Y H:i', strtotime($client['updated_at']))
                                : '-' ?>
                        </td>

                        <td>
                            <a href="view.php?id=<?= $client['id'] ?>">Apri</a>
                            |
                            <a href="edit.php?id=<?= $client['id'] ?>">Modifica</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$clients): ?>
                    <tr>
                        <td colspan="6">Nessun cliente trovato.</td>
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
