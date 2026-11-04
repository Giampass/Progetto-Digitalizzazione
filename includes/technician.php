
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentTechnicianId(): ?int {
    return isset($_SESSION['technician_id'])
        ? (int) $_SESSION['technician_id']
        : null;
}

function requireTechnician(): int {
    $id = currentTechnicianId();

    if (!$id) {
        die('Seleziona prima un tecnico.');
    }

    return $id;
}

function logActivity(
    PDO $pdo,
    string $action,
    string $entity,
    int $entityId,
    string $description
): void {

    $stmt = $pdo->prepare("
        INSERT INTO activity_logs
        (technician_id, action, entity_type, entity_id, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        currentTechnicianId(),
        $action,
        $entity,
        $entityId,
        $description
    ]);
}

// Cambio de tecnico
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['select_technician'])) {

    $id = filter_var(
        $_POST['technician_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $stmt = $pdo->prepare("
        SELECT id FROM technicians
        WHERE id = ? AND active = 1
    ");

    $stmt->execute([$id]);

    if ($stmt->fetch()) {
        session_regenerate_id(true);
        $_SESSION['technician_id'] = (int) $id;
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Obtener tecnico actual
$currentTechnician = null;

if (currentTechnicianId()) {
    $stmt = $pdo->prepare("
        SELECT id, name FROM technicians
        WHERE id = ? AND active = 1
    ");

    $stmt->execute([currentTechnicianId()]);
    $currentTechnician = $stmt->fetch();

    if (!$currentTechnician) {
        unset($_SESSION['technician_id']);
    }
}

// Listado de tecnicos
$technicians = $pdo->query("
    SELECT id, name
    FROM technicians
    WHERE active = 1
    ORDER BY name
")->fetchAll();

