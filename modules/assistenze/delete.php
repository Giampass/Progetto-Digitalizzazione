
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$technicianId = requireTechnician();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.id, a.assistance_date, a.reported_problem,
           c.company_name, p.brand, p.model
    FROM assistances a
    INNER JOIN clients c ON c.id = a.client_id
    INNER JOIN printers p ON p.id = a.printer_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$assistance = $stmt->fetch();

if (!$assistance) {
    http_response_code(404);
    die('Intervento non trovato.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Richiesta non valida. Ricarica la pagina.');
    }

    try {
        $pdo->beginTransaction();

        logActivity(
            $pdo,
            'DELETE',
            'assistance',
            $id,
            'Intervento eliminato per il cliente ' . $assistance['company_name']
        );

        $stmt = $pdo->prepare("DELETE FROM assistances WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        header('Location: index.php?deleted=1');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());
        $error = 'Errore durante l’eliminazione dell’intervento.';
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elimina assistenza | TechSupport</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Elimina intervento</h1>
                <p>Conferma l'eliminazione</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="detail-card delete-confirmation">

                <h3>Conferma eliminazione</h3>

                <p>
                    Stai per eliminare definitivamente questo intervento:
                </p>

                <p>
                    <strong>Cliente:</strong>
                    <?= htmlspecialchars($assistance['company_name']) ?>
                </p>

                <p>
                    <strong>Stampante:</strong>
                    <?= htmlspecialchars($assistance['brand'] . ' ' . $assistance['model']) ?>
                </p>

                <p>
                    <strong>Data:</strong>
                    <?= date('d/m/Y H:i', strtotime($assistance['assistance_date'])) ?>
                </p>

                <p>
                    <strong>Problema:</strong>
                    <?= htmlspecialchars($assistance['reported_problem']) ?>
                </p>

                <p class="warning-text">
                    Attenzione: questa operazione è definitiva.
                    Il registro attività conserverà l'evento di eliminazione,
                    ma i dati dell'intervento non saranno più disponibili.
                </p>

                <?php if ($error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="form-actions">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <a href="view.php?id=<?= (int)$id ?>" class="btn-secondary">
                        Annulla
                    </a>

                    <button type="submit" class="btn-danger">
                        Conferma eliminazione
                    </button>
                </form>

            </div>

        </section>
    </main>
</div>

</body>
</html>
