<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

// Optionnel : vérifier le rôle
if ($_SESSION['user']['role'] !== 'administrateur') {
    die('Accès interdit.');
}

// Connexion PDO
try {
    $bdd = new PDO(
        'mysql:host=localhost;dbname=caisse;charset=utf8', 'root', '');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// Variables pour les messages et les résultats
$message = '';
$messageType = '';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add_user') {
            // Ajouter un utilisateur
            $userType = $_POST['user_type'];
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $login = trim($_POST['login']);
            $mdp = $_POST['mdp'];
            
            // Générer un ID unique pour l'utilisateur
            $id_user= strtolower(substr($prenom, 0, 1) . substr($nom, 0, 1)) . rand(10, 99);
            
            if ($userType === 'caissier') {
                $stmt = $bdd->prepare("INSERT INTO Caisse (id_user, nom, prenom, login, mdp) VALUES (?, ?, ?, ?, ?)");
            } else {
                $stmt = $bdd->prepare("INSERT INTO Comptable (id_user, nom, prenom, login, mdp) VALUES (?, ?, ?, ?, ?)");
            }
            
            $stmt->execute([$id_user, $nom, $prenom, $login, $mdp]);
            $message = "✓ Utilisateur $nom $prenom ajouté avec succès !";
            $messageType = 'success';
            
        } elseif ($action === 'delete_user') {
            // Supprimer un utilisateur
            $userType = $_POST['user_type'];
            $userId = $_POST['user_id'];
            
            if ($userType === 'caissier') {
                $bdd->prepare("DELETE FROM Caisse WHERE id_user = ?")->execute([$userId]);
            } else {
                $bdd->prepare("DELETE FROM Comptable WHERE id_user = ?")->execute([$userId]);
            }
            
            $message = "✓ Utilisateur supprimé avec succès !";
            $messageType = 'success';
            
        } elseif ($action === 'execute_query') {
            // Exécuter une requête SQL
            $sql = trim($_POST['sql_query']);
            if (!empty($sql)) {
                $stmt = $bdd->prepare($sql);
                $stmt->execute();
                $queryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($queryResult)) {
                    $message = "✓ Requête exécutée. " . $stmt->rowCount() . " ligne(s) affectée(s).";
                }
                $messageType = 'success';
            }
        } elseif ($action === 'reset_password') {
            // Réinitialiser le mot de passe d'un utilisateur
            $resetType = $_POST['reset_type'];
            $userId = $_POST['reset_id'];
            $newPwd = $_POST['reset_pwd'];
            
            $table = ($resetType === 'caissier') ? 'Caisse' : 'Comptable';
            $stmt = $bdd->prepare("UPDATE `$table` SET mdp = ? WHERE id_user = ?");
            $stmt->execute([$newPwd, $userId]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✓ Mot de passe réinitialisé pour $userId";
                $messageType = 'success';
            } else {
                $message = "⚠ Utilisateur $userId non trouvé.";
                $messageType = 'error';
            }
            
        } elseif ($action === 'export_users') {
            // Exporter les utilisateurs
            $exportType = $_POST['export_type'];
            $users = [];
            
            if ($exportType === 'all' || $exportType === 'caissier') {
                $stmt = $bdd->prepare("SELECT id_user, nom, prenom, login FROM Caisse ORDER BY nom");
                $stmt->execute();
                $caissiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($caissiers as $c) {
                    $c['type'] = 'Caissier';
                    $users[] = $c;
                }
            }
            
            if ($exportType === 'all' || $exportType === 'comptable') {
                $stmt = $bdd->prepare("SELECT id_user, nom, prenom, login FROM Comptable ORDER BY nom");
                $stmt->execute();
                $comptables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($comptables as $c) {
                    $c['type'] = 'Comptable';
                    $users[] = $c;
                }
            }
            
            // Créer un fichier texte à télécharger
            $filename = 'utilisateurs_' . date('Ymd_His') . '.txt';
            header('Content-Type: text/plain; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            echo "EXPORT DES UTILISATEURS\n";
            echo "Généré le : " . date('d/m/Y H:i:s') . "\n";
            echo str_repeat("=", 100) . "\n\n";
            
            foreach ($users as $user) {
                echo "ID: " . $user['id_user'] . " | Type: " . $user['type'] . "\n";
                echo "  Nom: " . $user['nom'] . "\n";
                echo "  Prénom: " . $user['prenom'] . "\n";
                echo "  Login: " . $user['login'] . "\n";
                echo "\n";
            }
            exit();
            
        } elseif ($action === 'maintenance') {
            // Opérations de maintenance
            $operation = $_POST['maintenance_op'];
            
            if ($operation === 'check_db') {
                $result = $bdd->query("CHECK TABLE Caisse, Comptable, Administrateur")->fetchAll(PDO::FETCH_ASSOC);
                $message = "✓ Vérification complétée : " . count($result) . " table(s) vérifiée(s).";
                $messageType = 'success';
            } elseif ($operation === 'optimize') {
                $bdd->query("OPTIMIZE TABLE Caisse, Comptable, Administrateur");
                $message = "✓ Optimisation des tables complétée.";
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        $message = "✗ Erreur : " . $e->getMessage();
        $messageType = 'error';
    }
}

// Vérifier si on demande à voir une table ou sa structure
if (isset($_GET['view_table'])) {
    $tableName = $_GET['view_table'];
    try {
        $stmt = $bdd->prepare("SELECT * FROM `$tableName` LIMIT 100");
        $stmt->execute();
        $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h1>Contenu de la table: " . htmlspecialchars($tableName) . "</h1>";
        if (!empty($tableData)) {
            echo "<table class='expenses-table'><thead><tr>";
            foreach (array_keys($tableData[0]) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr></thead><tbody>";
            foreach ($tableData as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
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
} elseif (isset($_GET['show_structure'])) {
    $tableName = $_GET['show_structure'];
    try {
        $stmt = $bdd->prepare("DESCRIBE `$tableName`");
        $stmt->execute();
        $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h1>Structure de la table: " . htmlspecialchars($tableName) . "</h1>";
        echo "<table class='expenses-table'><thead><tr>";
        echo "<th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th>";
        echo "</tr></thead><tbody>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<br><a href='admin.php'>Retour à l'administration</a>";
        exit();
    } catch (PDOException $e) {
        die('Erreur lors de la récupération des données : ' . $e->getMessage());
    }
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

    <?php if (!empty($message)): ?>
        <div style="margin: 20px auto; max-width: 1200px; padding: 15px; border-radius: 8px; <?php echo $messageType === 'success' ? 'background: #d4edda; border: 1px solid #c3e6cb; color: #155724;' : 'background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="tabs">
            <button class="tab-button active" data-tab="users" onclick="switchTab('users')">Gestion Utilisateurs</button>
            <button class="tab-button" data-tab="caissiers" onclick="switchTab('caissiers')">Caissiers</button>
            <button class="tab-button" data-tab="comptables" onclick="switchTab('comptables')">Comptables</button>
            <button class="tab-button" data-tab="database" onclick="switchTab('database')">Base de Données</button>
            <button class="tab-button" data-tab="stats" onclick="switchTab('stats')">Statistiques</button>
        </div>

        <!-- TAB 1: Gestion Utilisateurs -->
        <div id="users" class="tab-content active">
            <h2>Gestion des Utilisateurs</h2>

            <div style="margin-bottom: 20px;">
                <a href="admin.php?action=add_user_form" class="submit-btn"> ➕ Ajouter un Utilisateur </a>
            </div>

            <div id="addUserForm" style="display: none; margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h3>Ajouter un Nouvel Utilisateur</h3>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="add_user">
                    <div class="expense-form">
                        <div class="form-group">
                            <label for="user_type">Type d'utilisateur</label>
                            <select id="user_type" name="user_type" required>
                                <option value="caissier">Caissier</option>
                                <option value="comptable">Comptable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" required>
                        </div>
                        <div class="form-group">
                            <label for="login">Login</label>
                            <input type="text" id="login" name="login" required>
                        </div>
                        <div class="form-group">
                            <label for="mdp">Mot de passe</label>
                            <input type="password" id="mdp" name="mdp" required>
                        </div>
                        <div class="form-group full">
                            <button type="submit" class="submit-btn">Ajouter l'Utilisateur</button>
                            <button type="button" class="submit-btn" style="background: #6c757d; margin-left: 10px;" onclick="hideAddUserForm()">Annuler</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB 2: Liste des Caissiers -->
        <div id="caissiers" class="tab-content">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caissiers as $caissier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($caissier['id_user']); ?></td>
                                <td><?php echo htmlspecialchars($caissier['nom']); ?></td>
                                <td><?php echo htmlspecialchars($caissier['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($caissier['login']); ?></td>
                                <td>
                                    <a href="admin.php?action=edit_user&type=caissier&id=<?php echo urlencode($caissier['id_user']); ?>" class="btn-edit"> Modifier </a>
                                    <a href="admin.php?action=delete_user&type=caissier&id=<?php echo urlencode($caissier['id_user']); ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">Supprimer </a> 
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Aucun caissier trouvé.</p>
            <?php endif; ?>
        </div>

        <!-- TAB 3: Liste des Comptables -->
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comptables as $comptable): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comptable['id_user']); ?></td>
                                <td><?php echo htmlspecialchars($comptable['nom']); ?></td>
                                <td><?php echo htmlspecialchars($comptable['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($comptable['login']); ?></td>
                                <td>
                                    <a href="admin.php?action=edit_user&type=caissier&id=<?php echo urlencode($caissier['id_user']); ?>" class="btn-edit"> Modifier </a>
                                    <a href="admin.php?action=delete_user&type=caissier&id=<?php echo urlencode($caissier['id_user']); ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">Supprimer </a> 
                                </td>
                                <?php
                                    if (isset($_GET['action']) && $_GET['action'] === 'delete_user') {
                                        $type = $_GET['type'];
                                        $id = $_GET['id'];
                                        if ($type === 'caissier') {
                                            $bdd->prepare("DELETE FROM Caisse WHERE id_user = ?")->execute([$id]);
                                        } else {
                                            $bdd->prepare("DELETE FROM Comptable WHERE id_user = ?")->execute([$id]);
                                        }
                                        header('Location: admin.php');
                                        exit();
                                    }
                                    elseif (isset($_GET['action']) && $_GET['action'] === 'edit_user') {
                                       $type = $_GET['type'];
                                        $id = $_GET['id_user'];
                                    }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666;">Aucun comptable trouvé.</p>
            <?php endif; ?>
        </div>

        <!-- TAB 4: Gestion Base de Données -->
        <div id="database" class="tab-content">
            <h2>Tables de la Base de Données</h2>
            <?php
            $tablesStmt = $bdd->query("SHOW TABLES");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            ?>
            <table class="expenses-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Type</th>
                        <th>Collation</th>
                        <th>Nombre de lignes</th>
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
                            <td><?php echo htmlspecialchars($status['Rows']); ?></td>
                            <td>
                                <a href="admin.php?view_table=<?php echo urlencode($status['Name']); ?>" class="btn-view">Voir Contenu</a>
                                <a href="admin.php?show_structure=<?php echo urlencode($status['Name']); ?>" class="btn-view">Voir Structure</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            

        <!-- TAB 5: Statistiques -->
        <div id="stats" class="tab-content">
            <h2>Tableau de Bord</h2>
            <?php
            $totalCaissiers = $bdd->query('SELECT COUNT(*) FROM Caisse')->fetchColumn();
            $totalComptables = $bdd->query('SELECT COUNT(*) FROM Comptable')->fetchColumn();
            $totalProduits = $bdd->query('SELECT COUNT(*) FROM Produit')->fetchColumn();
            $totalStock = $bdd->query('SELECT SUM(stock) FROM Produit')->fetchColumn() ?? 0;
            $totalVentes = $bdd->query('SELECT COUNT(*) FROM Vente')->fetchColumn();
            $totalVentesSemaine = $bdd->query('SELECT COUNT(*) FROM Vente WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();
            ?>
            <!-- Cartes -->
            <div class="stats-grid">
                <div class="stat-card stat-orange">
                    <h3>👥 Caissiers</h3>
                    <div class="stat-number"><?php echo $totalCaissiers; ?></div>
                </div>
                <div class="stat-card stat-gray">
                    <h3>📊 Comptables</h3>
                    <div class="stat-number"><?php echo $totalComptables; ?></div>
                </div>
                <div class="stat-card stat-blue">
                    <h3>🛒 Produits</h3>
                    <div class="stat-number"><?php echo $totalProduits; ?></div>
                </div>
                <div class="stat-card stat-green">
                    <h3>📦 Stock Total</h3>
                    <div class="stat-number"><?php echo $totalStock; ?></div>
                </div>
                <div class="stat-card stat-yellow">
                    <h3>💳 Ventes Totales</h3>
                    <div class="stat-number"><?php echo $totalVentes; ?></div>
                </div>
                <div class="stat-card stat-red">
                    <h3>📅 Ventes Semaine</h3>
                    <div class="stat-number"><?php echo $totalVentesSemaine; ?></div>
                </div>
            </div>

            <!-- Export -->
            <div class="stats-section">
                <h3>📥 Export des Utilisateurs</h3>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="export_users">
                    <div class="expense-form">
                        <div class="form-group">
                            <label>Type d'export</label>
                            <select name="export_type" required>
                                <option value="all">Tous les utilisateurs</option>
                                <option value="caissier">Caissiers uniquement</option>
                                <option value="comptable">Comptables uniquement</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="submit-btn">
                                Exporter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Maintenance -->
            <div class="stats-section">
                <h3>🛠 Maintenance de la Base</h3>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="maintenance">
                    <div class="expense-form">
                        <div class="form-group">
                            <label>Opération</label>
                            <select name="maintenance_op" required>
                                <option value="check_db">Vérifier les tables</option>
                                <option value="optimize">Optimiser les tables</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="submit-btn">
                                Exécuter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <!-- Stock détaillé -->
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
                    <tbody>
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

    <script src="admin.js"></script>
</body>
</html>