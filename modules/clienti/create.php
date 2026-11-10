
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $technicianId = requireTechnician();

    $company = trim($_POST['company_name'] ?? '');

    if ($company === '') {
        $error = 'La ragione sociale è obbligatoria.';
    } else {

        try {
            $stmt = $pdo->prepare("
                INSERT INTO clients (
                    company_name, contact_person, phone,
                    email, address, city, province,
                    vat_number, notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $company,
                trim($_POST['contact_person'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['address'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['province'] ?? ''),
                trim($_POST['vat_number'] ?? ''),
                trim($_POST['notes'] ?? '')
            ]);

            $id = (int) $pdo->lastInsertId();

            logActivity(
                $pdo,
                'CREATE',
                'client',
                $id,
                "Creato cliente: $company"
            );

            header("Location: view.php?id=$id");
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Errore durante la registrazione del cliente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Nuovo cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app">

<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

    <header class="topbar">
        <div>
            <h1>Nuovo cliente</h1>
            <p>Inserimento anagrafica cliente</p>
        </div>
    </header>

    <section class="panel">

        <?php if ($error): ?>
            <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="client-form">

            <label>Ragione sociale *</label>
            <input name="company_name" required
                value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">

            <label>Persona di riferimento</label>
            <input name="contact_person"
                value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">

            <label>Telefono</label>
            <input name="phone"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

            <label>Email</label>
            <input type="email" name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label>Partita IVA</label>
            <input name="vat_number"
                value="<?= htmlspecialchars($_POST['vat_number'] ?? '') ?>">

            <label>Indirizzo</label>
            <input name="address"
                value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">

            <label>Città</label>
            <input name="city"
                value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">

            <label>Provincia</label>
            <input name="province"
                value="<?= htmlspecialchars($_POST['province'] ?? '') ?>">

            <label>Note</label>
            <textarea name="notes"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

            <div class="form-actions">
                <button class="btn-primary" type="submit">
                    Salva cliente
                </button>

                <a href="index.php">Annulla</a>
            </div>

        </form>

    </section>

</main>
</div>

</body>
</html>
