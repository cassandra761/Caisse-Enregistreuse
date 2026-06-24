/**
 * Met à jour le statut d'une vente via AJAX
 * puis met à jour la ligne en live sans rechargement.
 */
function updateStatut(idVente, statut, btn) {

    btn.disabled = true;

    fetch('comptable.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_statut', id_vente: idVente, statut: statut })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Erreur serveur');

        // Mettre à jour la ligne dans le tableau
        const row   = btn.closest('tr');
        const badge = row.querySelector('.badge');
        const cell  = row.querySelector('.actions-cell');

        const labels = { en_attente: 'En attente', valide: 'Validée', refuse: 'Refusée' };
        badge.textContent = labels[statut] ?? statut;
        badge.className   = 'badge badge-' + statut;

        if (statut === 'en_attente') {
            cell.innerHTML = `
                <button class="btn btn-valider" onclick="updateStatut(${idVente}, 'valide', this)">✓ Valider</button>
                <button class="btn btn-refuser" onclick="updateStatut(${idVente}, 'refuse', this)">✕ Refuser</button>
            `;
        } else {
            cell.innerHTML = `
                <button class="btn btn-reset" onclick="updateStatut(${idVente}, 'en_attente', this)">↩ Remettre</button>
            `;
        }

        showToast('Statut mis à jour.', 'success');
    })
    .catch(err => {
        btn.disabled = false;
        showToast(err.message, 'error');
    });
}

// ── Toast ───────────────────────────────────────────────
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className   = 'toast show ' + type;

    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.className = 'toast';
    }, 3000);
}