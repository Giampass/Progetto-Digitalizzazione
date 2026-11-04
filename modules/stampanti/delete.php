
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
    SELECT id, brand, model, serial_number
    FROM printers
    WHERE id = ?
");
$stmt->execute([$id]);
$printer = $stmt->fetch();

if (!$printer) {
    http_response_code(404);
    die('Stampante non trovata.');
}

// Controlla se esistono interventi collegati
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assistances
    WHERE printer_id = ?
");
$stmt->execute([$id]);
$assistanceCount = (int)$stmt->fetchColumn();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Richiesta non valida. Ricarica la pagina.');
    }

    if ($assistanceCount > 0) {
        $error = 'Impossibile eliminare questa stampante: sono presenti interventi associati.';
    } else {

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM printers
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            logActivity(
                $pdo,
                'DELETE',
                'printer',
                $id,
                "Stampante eliminata: {$printer['brand']} {$printer['model']}"
            );

            $pdo->commit();

            header('Location: index.php?deleted=1');
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = 'Errore durante l’eliminazione. La stampante potrebbe essere collegata ad altri dati.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elimina stampante - Digitalizzazione</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Elimina stampante</h1>
                <p>Conferma dell'eliminazione</p>
            </div>

            <?php include '../../includes/technician-selector.php'; ?>

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="detail-card">

                <h3>Conferma eliminazione</h3>

                <p>
                    Stai per eliminare:
                    <strong>
                        <?= htmlspecialchars($printer['brand'] . ' ' . $printer['model']) ?>
                    </strong>
                </p>

                <p>
                    Numero seriale:
                    <?= htmlspecialchars($printer['serial_number'] ?: 'Non disponibile') ?>
                </p>

                <?php if ($error !== ''): ?>
                    <div class="form-error">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($assistanceCount > 0): ?>

                    <p class="empty-message">
                        Questa stampante ha <?= $assistanceCount ?> interventi associati.
                        Non è possibile eliminarla per proteggere lo storico.
                    </p>

                    <a href="view.php?id=<?= $id ?>" class="btn-secondary">
                        Torna alla scheda
                    </a>

                <?php else: ?>

                    <p class="empty-message">
                        Attenzione: questa operazione non può essere annullata.
                    </p>

                    <form method="POST" class="form-actions">

                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <a href="view.php?id=<?= $id ?>" class="btn-secondary">
                            Annulla
                        </a>

                        <button type="submit" class="btn-danger"
                                onclick="return confirm('Confermi definitivamente l’eliminazione?')">
                            Elimina stampante
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

</body>
</html>
