
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireTechnician();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            DELETE FROM clients WHERE id = ?
        ");
        $stmt->execute([$id]);

        logActivity(
            $pdo,
            'DELETE',
            'client',
            $id,
            "Eliminato cliente: " . $client['company_name']
        );

        $pdo->commit();

        header('Location: index.php');
        exit;

    } catch (PDOException $e) {

        $pdo->rollBack();

        error_log($e->getMessage());

        $error = 'Impossibile eliminare il cliente. Potrebbero esserci stampanti o assistenze associate.';
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Elimina cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app">

<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

<header class="topbar">
    <div>
        <h1>Elimina cliente</h1>
        <p>Conferma eliminazione</p>
    </div>
</header>

<section class="panel">

    <h2>Sei sicuro di voler eliminare questo cliente?</h2>

    <p class="notes">
        <?= htmlspecialchars($client['company_name']) ?>
    </p>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" class="form-actions">
        <button type="submit" class="btn-danger">
            Conferma eliminazione
        </button>

        <a href="view.php?id=<?= $id ?>">Annulla</a>
    </form>

</section>

</main>
</div>

</body>
</html>
