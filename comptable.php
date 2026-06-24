<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['user']['role'] !== 'comptable') {
    die('Accès interdit.');
}
 
// Connexion PDO
try {
    $bdd = new PDO(
        'mysql:host=localhost;dbname=caisse;charset=utf8', 'root', '');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// AJAX GET : polling nouvelles ventes en attente
if (isset($_GET['action']) && $_GET['action'] === 'poll_ventes') {
    header('Content-Type: application/json');
    $depuis = (int)($_GET['depuis'] ?? 0); // dernier id_vente connu
    $rows = $bdd->prepare("
        SELECT v.id_vente, v.date_vente, v.total, v.statut, c.nom, c.prenom
        FROM vente v
        JOIN Caisse c ON c.id_user = v.id_user
        WHERE v.id_vente > ?
        ORDER BY v.id_vente ASC
    ");
    $rows->execute([$depuis]);
    echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
    exit();
}

// AJAX : changer le statut d'une vente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_statut') {
        $id    = (int) $_POST['id_vente'];
        $statut = $_POST['statut']; // 'valide' ou 'refuse'

        if (!in_array($statut, ['en_attente', 'valide', 'refuse'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Statut invalide'
        ]);
        exit();
}

        $stmt = $bdd->prepare("UPDATE vente SET statut = ? WHERE id_vente = ?");
        $stmt->execute([$statut, $id]);
        echo json_encode(['success' => true]);
        exit();
    }
    exit();
}

// Filtre statut
$filtre = $_GET['filtre'] ?? 'tous';
$allowed = ['tous', 'en_attente', 'valide', 'refuse'];
if (!in_array($filtre, $allowed)) $filtre = 'tous';

$where = $filtre !== 'tous' ? "WHERE v.statut = :statut" : "";

$sql = "
    SELECT v.id_vente, v.date_vente, v.total, v.statut,
           c.nom, c.prenom
    FROM vente v
    JOIN Caisse c ON c.id_user = v.id_user
    $where
    ORDER BY v.date_vente DESC, v.id_vente DESC
";

$stmt = $bdd->prepare($sql);

if ($filtre !== 'tous') {
    $stmt->bindValue(':statut', $filtre);
}

$stmt->execute();
$ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $bdd->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END),0) AS en_attente,
        COALESCE(SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END),0) AS valide,
        COALESCE(SUM(CASE WHEN statut = 'refuse' THEN 1 ELSE 0 END),0) AS refuse,
        COALESCE(SUM(CASE WHEN statut = 'valide' THEN total ELSE 0 END),0) AS ca_valide
    FROM vente
")->fetch(PDO::FETCH_ASSOC);

$maxIdVente = (int)($bdd->query("SELECT COALESCE(MAX(id_vente),0) FROM vente")->fetchColumn());

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptable – GSB</title>
    <link rel="stylesheet" href="comptable.css">
</head>
<body>

<header class="header">
    <div class="profile-badge">
        <div class="profile-icon">📊</div>
        <div class="profile-text">
            <h3><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></h3>
            <p>Comptable</p>
        </div>
    </div>
    <a href="logout.php" class="logout-btn">Déconnexion</a>
</header>

<main>
    <h1>Gestion des Ventes</h1>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi kpi-orange">
            <span class="kpi-label">En attente</span>
            <span class="kpi-value"><?php echo $stats['en_attente']; ?></span>
        </div>
        <div class="kpi kpi-green">
            <span class="kpi-label">Validées</span>
            <span class="kpi-value"><?php echo $stats['valide']; ?></span>
        </div>
        <div class="kpi kpi-red">
            <span class="kpi-label">Refusées</span>
            <span class="kpi-value"><?php echo $stats['refuse']; ?></span>
        </div>
        <div class="kpi kpi-blue">
            <span class="kpi-label">CA validé</span>
            <span class="kpi-value"><?php echo number_format($stats['ca_valide'], 2, ',', ' '); ?> €</span>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters">
        <?php
        $labels = ['tous' => 'Toutes', 'en_attente' => 'En attente', 'valide' => 'Validées', 'refuse' => 'Refusées'];
        foreach ($labels as $key => $label):
        ?>
            <a href="?filtre=<?php echo $key; ?>"
               class="filter-btn <?php echo $filtre === $key ? 'active' : ''; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tableau -->
    <div class="table-wrap">
        <?php if (empty($ventes)): ?>
            <p class="empty">Aucune vente à afficher.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Caissier</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventes as $v): ?>
                <tr data-id="<?php echo $v['id_vente']; ?>">
                    <td>#<?php echo $v['id_vente']; ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($v['date_vente']))); ?></td>
                    <td><?php echo htmlspecialchars($v['prenom'] . ' ' . $v['nom']); ?></td>
                    <td class="montant"><?php echo number_format($v['total'], 2, ',', ' '); ?> €</td>
                    <td>
                        <span class="badge badge-<?php echo $v['statut']; ?>">
                            <?php
                            $labels_statut = ['en_attente' => 'En attente', 'valide' => 'Validée', 'refuse' => 'Refusée'];
                            echo $labels_statut[$v['statut']] ?? $v['statut'];
                            ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <?php if ($v['statut'] === 'en_attente'): ?>
                            <button class="btn btn-valider" onclick="updateStatut(<?php echo $v['id_vente']; ?>, 'valide', this)">✓ Valider</button>
                            <button class="btn btn-refuser" onclick="updateStatut(<?php echo $v['id_vente']; ?>, 'refuse', this)">✕ Refuser</button>
                        <?php else: ?>
                            <button class="btn btn-reset" onclick="updateStatut(<?php echo $v['id_vente']; ?>, 'en_attente', this)">↩ Remettre</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<!-- Toast notification -->
<div id="toast" class="toast"></div>

<script src="comptable.js"></script>
<script>
// ── Polling : nouvelles ventes toutes les 5 s ──
let dernierIdVente = <?php echo $maxIdVente; ?>;
let filtre = '<?php echo $filtre; ?>';

function pollNouvellesVentes() {
    fetch(`comptable.php?action=poll_ventes&depuis=${dernierIdVente}`)
        .then(r => r.json())
        .then(nouvelles => {
            if (!nouvelles.length) return;

            // Mettre à jour le dernier id connu
            dernierIdVente = Math.max(...nouvelles.map(v => parseInt(v.id_vente)));

            // Afficher une notification discrète
            const nb = nouvelles.filter(v => v.statut === 'en_attente').length;
            if (nb > 0) showToast(`${nb} nouvelle${nb > 1 ? 's' : ''} vente${nb > 1 ? 's' : ''} en attente`, 'success');

            // Ajouter les nouvelles lignes si le filtre le permet
            const tbody = document.querySelector('table tbody');
            if (!tbody) return; // table vide → recharger

            nouvelles.forEach(v => {
                // Ne pas dupliquer une ligne déjà présente
                if (document.querySelector(`tr[data-id="${v.id_vente}"]`)) return;
                // Respecter le filtre actif
                if (filtre !== 'tous' && v.statut !== filtre) return;

                const labelsStatut = { en_attente: 'En attente', valide: 'Validée', refuse: 'Refusée' };
                const actions = v.statut === 'en_attente'
                    ? `<button class="btn btn-valider" onclick="updateStatut(${v.id_vente},'valide',this)">✓ Valider</button>
                       <button class="btn btn-refuser" onclick="updateStatut(${v.id_vente},'refuse',this)">✕ Refuser</button>`
                    : `<button class="btn btn-reset"   onclick="updateStatut(${v.id_vente},'en_attente',this)">↩ Remettre</button>`;

                const date = new Date(v.date_vente).toLocaleDateString('fr-FR');
                const total = parseFloat(v.total).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' €';

                const tr = document.createElement('tr');
                tr.dataset.id = v.id_vente;
                tr.innerHTML = `
                    <td>#${v.id_vente}</td>
                    <td>${date}</td>
                    <td>${v.prenom} ${v.nom}</td>
                    <td class="montant">${total}</td>
                    <td><span class="badge badge-${v.statut}">${labelsStatut[v.statut] ?? v.statut}</span></td>
                    <td class="actions-cell">${actions}</td>
                `;
                tr.style.animation = 'highlight 1s ease';
                tbody.insertBefore(tr, tbody.firstChild);
            });
        })
        .catch(() => {});
}

setInterval(pollNouvellesVentes, 5000);
</script>
<style>
@keyframes highlight {
    from { background: #fff3e0; }
    to   { background: transparent; }
}
</style>
</body>
</html>