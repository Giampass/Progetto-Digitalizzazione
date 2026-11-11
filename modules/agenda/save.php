
<?php
require_once '../../config/database.php';
require_once '../../includes/technician.php';

$technicianId = requireTechnician();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verifica il token di sicurezza
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_agenda']) ||
    !hash_equals($_SESSION['csrf_agenda'], $csrfToken)
) {
    die('Richiesta non valida. Aggiorna la pagina e riprova.');
}

// Recupera e valida i dati
$title = trim($_POST['title'] ?? '');
$listDate = $_POST['list_date'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if ($title === '') {
    $title = 'Lista attività - ' . date('d/m/Y', strtotime($listDate));
}

if (mb_strlen($title) > 150) {
    die('Il nome della lista non può superare 150 caratteri.');
}

$date = DateTime::createFromFormat('!Y-m-d', $listDate);

if (!$date || $date->format('Y-m-d') !== $listDate) {
    die('La data inserita non è valida.');
}

// Recupera il nome del tecnico
$technicianName = $currentTechnician['name'] ?? null;

if (!$technicianName) {
    die('Tecnico non valido. Seleziona nuovamente il tecnico.');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO agenda_lists
            (title, list_date, technician_id, technician_name, notes)
        VALUES
            (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $title,
        $listDate,
        $technicianId,
        $technicianName,
        $notes !== '' ? $notes : null
    ]);

    // Evita il reinvio del modulo
    unset($_SESSION['csrf_agenda']);

    header('Location: index.php?created=1');
    exit;

} catch (PDOException $e) {
    die('Errore durante la creazione della lista attività.');
}
