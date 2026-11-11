
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$technicianId = requireTechnician();

if (empty($_SESSION['csrf_agenda'])) {
    $_SESSION['csrf_agenda'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova lista attività | SELCOCOPY</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Nuova lista attività</h1>
                <p>Crea una nuova giornata di lavoro</p>
            </div>

            <div class="date"><?= date('d/m/Y') ?></div>
        </header>

        <section class="content">

            <div class="agenda-form-container">

                <div class="section-header">
                    <div>
                        <h2>Informazioni lista</h2>
                        <p>Inserisci i dati generali della lista</p>
                    </div>
                </div>

                <form action="save.php" method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_agenda']) ?>"
                    >

                    <div class="form-group">
                        <label for="title">Nome lista</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="150"
                            placeholder="Es. Interventi zona Teramo"
                        >
                    </div>

                    <div class="form-group">
                        <label for="list_date">Data attività *</label>
                        <input
                            type="date"
                            id="list_date"
                            name="list_date"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="notes">Note generali</label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            placeholder="Eventuali informazioni aggiuntive..."
                        ></textarea>
                    </div>

                    <div class="agenda-form-actions">
                        <a href="index.php" class="btn-secondary">
                            Annulla
                        </a>

                        <button type="submit" class="btn-primary">
                            Crea lista
                        </button>
                    </div>

                </form>

            </div>

        </section>

    </main>
</div>

</body>
</html>
