
<aside class="sidebar">

    <div class="brand">
        <a href="/Progetto Digitalizzazione/" class="brand-link">
            <h2>SELCO<span>COPY s.r.l.</span></h2>
            <small>Assistenze e vendita macchine per ufficio</small>
        </a>
    </div>

    <nav>
        <a href="/Progetto Digitalizzazione/" class="nav-link">
            <span>▦</span> Dashboard
        </a>

        <a href="/Progetto Digitalizzazione/modules/clienti/" class="nav-link">
            <span>♙</span> Clienti
        </a>

        <a href="/Progetto Digitalizzazione/modules/stampanti/" class="nav-link">
            <span>▣</span> Stampanti
        </a>

        <a href="/Progetto Digitalizzazione/modules/assistenze/" class="nav-link">
            <span>⚙</span> Assistenze
        </a>

        <a href="/Progetto Digitalizzazione/modules/storico/" class="nav-link">
            <span>◷</span> Storico
        </a>
    </nav>

    <!-- Profilo tecnico -->
    <div class="sidebar-user">

        <details class="technician-profile">

            <summary>
                <div class="user-avatar">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="8" r="4"></circle>
                    </svg>
                </div>

                <div class="user-info">
                    <strong>
                        <?= htmlspecialchars(
                            $currentTechnician['name'] ?? 'Nessun tecnico',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                    <small>Tecnico selezionato</small>
                </div>

                <span class="profile-arrow">⌄</span>
            </summary>

            <div class="technician-dropdown">

                <form method="POST">

                    <label for="technician_id">
                        Cambia tecnico
                    </label>

                    <select name="technician_id"
                            id="technician_id"
                            required>

                        <option value="">Seleziona tecnico</option>

                        <?php foreach ($technicians as $tech): ?>
                            <option
                                value="<?= (int)$tech['id'] ?>"
                                <?= currentTechnicianId() === (int)$tech['id']
                                    ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($tech['name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <button type="submit" name="select_technician">
                        Cambia tecnico
                    </button>

                </form>

            </div>

        </details>

    </div>

    <div class="sidebar-footer">
        Sistema di assistenza tecnica
    </div>

</aside>
