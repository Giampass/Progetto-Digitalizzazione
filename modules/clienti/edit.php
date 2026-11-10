
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('Cliente non valido.');
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    die('Cliente non trovato.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $technicianId = requireTechnician();
    $company = trim($_POST['company_name'] ?? '');

    if ($company === '') {
        $error = 'La ragione sociale è obbligatoria.';
    } else {

        try {
            $stmt = $pdo->prepare("
                UPDATE clients SET
                    company_name = ?,
                    contact_person = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    city = ?,
                    province = ?,
                    vat_number = ?,
                    notes = ?
                WHERE id = ?
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
                trim($_POST['notes'] ?? ''),
                $id
            ]);

            logActivity(
                $pdo,
                'UPDATE',
                'client',
                $id,
                "Modificati dati cliente: $company"
            );

            header("Location: view.php?id=$id");
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Errore durante la modifica.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app">

<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

<header class="topbar">
    <div>
        <h1>Modifica cliente</h1>
        <p>Aggiornamento dati anagrafici</p>
    </div>
</header>

<section class="panel">

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" class="client-form">

        <?php
        $fields = [
            'company_name' => 'Ragione sociale',
            'contact_person' => 'Persona di riferimento',
            'phone' => 'Telefono',
            'email' => 'Email',
            'vat_number' => 'Partita IVA',
            'address' => 'Indirizzo',
            'city' => 'Città',
            'province' => 'Provincia'
        ];
        ?>

        <?php foreach ($fields as $field => $label): ?>
            <label><?= $label ?><?= $field === 'company_name' ? ' *' : '' ?></label>
            <input
                name="<?= $field ?>"
                value="<?= htmlspecialchars($_POST[$field] ?? $client[$field] ?? '') ?>"
                <?= $field === 'company_name' ? 'required' : '' ?>
                <?= $field === 'email' ? 'type="email"' : '' ?>
            >
        <?php endforeach; ?>

        <label>Note</label>
        <textarea name="notes"><?= htmlspecialchars($_POST['notes'] ?? $client['notes'] ?? '') ?></textarea>

        <div class="form-actions">
            <button class="btn-primary" type="submit">Salva modifiche</button>
            <a href="view.php?id=<?= $id ?>">Annulla</a>
        </div>

    </form>

</section>
</main>
</div>
</body>
</html>
