<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}
 
if ($_SESSION['user']['role'] !== 'administrateur') {
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
 
// CORRECTION 1 : suppression déplacée hors du bloc try/catch de connexion
if (isset($_GET['action']) && $_GET['action'] === 'delete_user') {
    $type = $_GET['type'] ?? '';
    $id   = $_GET['id']   ?? 0;
 
    try {
        if ($type === 'caissier') {
            $bdd->prepare("DELETE FROM Caisse WHERE id_user = ?")->execute([$id]);
        } else {
            $bdd->prepare("DELETE FROM Comptable WHERE id_user = ?")->execute([$id]);
        }
    } catch (PDOException $e) {
        die('Erreur lors de la suppression : ' . $e->getMessage());
    }
 
    header('Location: admin.php');
    exit();
}
 
// Affichage du contenu d'une table
if (isset($_GET['view_table'])) {
    $tableName = $_GET['view_table'];
    try {
        $stmt = $bdd->prepare("SELECT * FROM `$tableName` LIMIT 100");
        $stmt->execute();
        $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        echo "<h1>Contenu de la table : " . htmlspecialchars($tableName) . "</h1>";
        if (!empty($tableData)) {
            echo "<table class='expenses-table'><thead><tr>";
            foreach (array_keys($tableData[0]) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr></thead><tbody>";
            foreach ($tableData as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>La table est vide.</p>";
        }
        echo "<br><a href='admin.php'>Retour à l'administration</a>";
        exit();
    } catch (PDOException $e) {
        die('Erreur lors de la récupération des données : ' . $e->getMessage());
    }
}
 
// Affichage de la structure d'une table
if (isset($_GET['show_structure'])) {
    $tableName = $_GET['show_structure'];
    try {
        $stmt = $bdd->prepare("DESCRIBE `$tableName`");
        $stmt->execute();
        $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        echo "<h1>Structure de la table : " . htmlspecialchars($tableName) . "</h1>";
        echo "<table class='expenses-table'><thead><tr>";
        echo "<th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th>";
        echo "</tr></thead><tbody>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field'])          . "</td>";
            echo "<td>" . htmlspecialchars($column['Type'])           . "</td>";
            echo "<td>" . htmlspecialchars($column['Null'])           . "</td>";
            echo "<td>" . htmlspecialchars($column['Key'])            . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? '')  . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra'])          . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<br><a href='admin.php'>Retour à l'administration</a>";
        exit();
    } catch (PDOException $e) {
        die('Erreur lors de la récupération des données : ' . $e->getMessage());
    }
}

// AJAX : données live pour le dashboard
if (isset($_GET['action']) && $_GET['action'] === 'live_stats') {
    header('Content-Type: application/json');
    $data = [
        'stock'   => [],
        'totaux'  => [
            'caissiers'     => (int)$bdd->query('SELECT COUNT(*) FROM Caisse')->fetchColumn(),
            'comptables'    => (int)$bdd->query('SELECT COUNT(*) FROM Comptable')->fetchColumn(),
            'produits'      => (int)$bdd->query('SELECT COUNT(*) FROM Produit')->fetchColumn(),
            'stock_total'   => (int)($bdd->query('SELECT SUM(stock) FROM Produit')->fetchColumn() ?? 0),
            'ventes'        => (int)$bdd->query('SELECT COUNT(*) FROM Vente')->fetchColumn(),
            'ventes_semaine'=> (int)$bdd->query('SELECT COUNT(*) FROM Vente WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn(),
        ],
    ];
    $rows = $bdd->query("SELECT nom, stock FROM Produit ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $data['stock'][] = ['nom' => $r['nom'], 'stock' => (int)$r['stock']];
    }
    echo json_encode($data);
    exit();
}
?>
 
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration GSB</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="logo GSB.png">
</head>
<body>
    <div class="header-connected">
        <div class="user-info">
            <div class="profile-badge">
                <div class="profile-icon">👤</div>
                <div class="profile-text">
                    <h3><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></h3>
                    <p>Administrateur</p>
                </div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Déconnexion</a>
    </div>
 
    <h1>Gestion d'Administration</h1>
 
    <div class="container">
        <div class="tabs">
            <button class="tab-button active" data-tab="caissiers">Caissiers</button>
            <button class="tab-button" data-tab="comptables">Comptables</button>
            <button class="tab-button" data-tab="database">Base de Données</button>
            <button class="tab-button" data-tab="stats">Statistiques</button>
        </div>
 
        <!-- TAB 1 : Liste des Caissiers -->
        <div id="caissiers" class="tab-content active">
            <h2>Liste des Caissiers</h2>
            <?php
            $caissiersStmt = $bdd->query('SELECT * FROM Caisse ORDER BY nom, prenom');
            $caissiers = $caissiersStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($caissiers)): ?>
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caissiers as $caissier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($caissier['id_user']); ?></td>
                                <td><?php echo htmlspecialchars($caissier['nom']); ?></td>
                                <td><?php echo htmlspecialchars($caissier['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($caissier['login']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Aucun caissier trouvé.</p>
            <?php endif; ?>
        </div>
 
        <!-- TAB 2 : Liste des Comptables -->
        <div id="comptables" class="tab-content">
            <h2>Liste des Comptables</h2>
            <?php
            $comptablesStmt = $bdd->query('SELECT * FROM Comptable ORDER BY nom, prenom');
            $comptables = $comptablesStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($comptables)): ?>
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comptables as $comptable): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comptable['id_user']); ?></td>
                                <td><?php echo htmlspecialchars($comptable['nom']); ?></td>
                                <td><?php echo htmlspecialchars($comptable['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($comptable['login']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666;">Aucun comptable trouvé.</p>
            <?php endif; ?>
        </div>
 
        <!-- TAB 3 : Gestion Base de Données -->
        <div id="database" class="tab-content">
            <h2>Tables de la Base de Données</h2>
            <table class="expenses-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Type</th>
                        <th>Collation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusStmt = $bdd->query("SHOW TABLE STATUS");
                    $tableStatus = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($tableStatus as $status):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($status['Name']); ?></td>
                            <td><?php echo htmlspecialchars($status['Engine']); ?></td>
                            <td><?php echo htmlspecialchars($status['Collation']); ?></td>
                            <td>
                                <a href="admin.php?view_table=<?php echo urlencode($status['Name']); ?>" class="btn-view">Voir Contenu</a>
                                <a href="admin.php?show_structure=<?php echo urlencode($status['Name']); ?>" class="btn-view">Voir Structure</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
 
        <!-- TAB 4 : Statistiques -->
        <div id="stats" class="tab-content">
            <h2>Tableau de Bord</h2>
            <?php
            $totalCaissiers      = $bdd->query('SELECT COUNT(*) FROM Caisse')->fetchColumn();
            $totalComptables     = $bdd->query('SELECT COUNT(*) FROM Comptable')->fetchColumn();
            $totalProduits       = $bdd->query('SELECT COUNT(*) FROM Produit')->fetchColumn();
            $totalStock          = $bdd->query('SELECT SUM(stock) FROM Produit')->fetchColumn() ?? 0;
            $totalVentes         = $bdd->query('SELECT COUNT(*) FROM Vente')->fetchColumn();
            $totalVentesSemaine  = $bdd->query('SELECT COUNT(*) FROM Vente WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();
            ?>
            <div class="stats-grid">
                <div class="stat-card stat-orange">
                    <h3>👥 Caissiers</h3>
                    <div class="stat-number" id="kpi-caissiers"><?php echo $totalCaissiers; ?></div>
                </div>
                <div class="stat-card stat-gray">
                    <h3>📊 Comptables</h3>
                    <div class="stat-number" id="kpi-comptables"><?php echo $totalComptables; ?></div>
                </div>
                <div class="stat-card stat-blue">
                    <h3>🛒 Produits</h3>
                    <div class="stat-number" id="kpi-produits"><?php echo $totalProduits; ?></div>
                </div>
                <div class="stat-card stat-green">
                    <h3>📦 Stock Total</h3>
                    <div class="stat-number" id="kpi-stock"><?php echo $totalStock; ?></div>
                </div>
                <div class="stat-card stat-yellow">
                    <h3>💳 Ventes Totales</h3>
                    <div class="stat-number" id="kpi-ventes"><?php echo $totalVentes; ?></div>
                </div>
                <div class="stat-card stat-red">
                    <h3>📅 Ventes Semaine</h3>
                    <div class="stat-number" id="kpi-ventes-semaine"><?php echo $totalVentesSemaine; ?></div>
                </div>
            </div>
 
            <div class="stats-section">
                <h3>📦 Détail des Stocks</h3>
                <?php
                $produitsStmt = $bdd->query("SELECT nom, stock FROM Produit ORDER BY nom");
                $produits = $produitsStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Stock Disponible</th>
                        </tr>
                    </thead>
                    <tbody id="stock-tbody">
                        <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                <td><?php echo htmlspecialchars($produit['stock']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
 
    </div><!-- CORRECTION 4 : fermeture du div.container manquante ajoutée -->
 
    <script src="admin.js"></script>
    <script>
    // ── Rafraîchissement automatique du stock et des stats toutes les 5 s ──
    function refreshStats() {
        fetch('admin.php?action=live_stats')
            .then(r => r.json())
            .then(d => {
                // Cartes KPI
                const map = {
                    'kpi-caissiers':      d.totaux.caissiers,
                    'kpi-comptables':     d.totaux.comptables,
                    'kpi-produits':       d.totaux.produits,
                    'kpi-stock':          d.totaux.stock_total,
                    'kpi-ventes':         d.totaux.ventes,
                    'kpi-ventes-semaine': d.totaux.ventes_semaine,
                };
                for (const [id, val] of Object.entries(map)) {
                    const el = document.getElementById(id);
                    if (el && el.textContent != val) {
                        el.textContent = val;
                        el.classList.add('flash');
                        setTimeout(() => el.classList.remove('flash'), 800);
                    }
                }

                // Tableau stock détaillé
                const tbody = document.getElementById('stock-tbody');
                if (tbody) {
                    tbody.innerHTML = d.stock.map(p =>
                        `<tr><td>${p.nom}</td><td>${p.stock}</td></tr>`
                    ).join('');
                }
            })
            .catch(() => {}); // silencieux si la page n'est pas active
    }
    // Lancer uniquement sur l'onglet stats
    setInterval(refreshStats, 5000);
    </script>
</body>
</html>