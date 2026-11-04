
<div class="technician-selector">

    <span>Tecnico:</span>

    <form method="POST">
        <select name="technician_id" required>
            <option value="">Seleziona tecnico</option>

            <?php foreach ($technicians as $tech): ?>
                <option
                    value="<?= $tech['id'] ?>"
                    <?= currentTechnicianId() === (int)$tech['id']
                        ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($tech['name']) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <button type="submit" name="select_technician">
            Cambia
        </button>
    </form>

</div>