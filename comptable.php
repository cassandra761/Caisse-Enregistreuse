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
</body>
</html>