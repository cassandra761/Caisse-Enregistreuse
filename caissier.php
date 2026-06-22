<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

// Optionnel : vérifier le rôle
if ($_SESSION['user']['role'] !== 'caisse') {
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

// Récupération des produits
$produits = $bdd->query("SELECT * FROM produit")->fetchAll(PDO::FETCH_ASSOC);

//le panier 
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// TRAITEMENT DU PAIEMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'payer') {

    header('Content-Type: application/json');

    try {

        $panier = json_decode($_POST['panier'], true);

        if (!$panier || count($panier) === 0) {
            throw new Exception("Panier vide");
        }

        $bdd->beginTransaction();

        $total = 0;

        foreach ($panier as $article) {

            $verif = $bdd->prepare("
                SELECT stock
                FROM produit
                WHERE id_produit = ?
            ");

            $verif->execute([$article['id']]);

            $stock = $verif->fetchColumn();

            if ($stock < $article['qty']) {
                throw new Exception(
                    "Stock insuffisant pour le produit ID "
                    . $article['id']
                );
            }

            $total += $article['price'] * $article['qty'];
        }

        $vente = $bdd->prepare("
            INSERT INTO vente(
                id_user,
                date_vente,
                total,
                statut
            )
            VALUES(
                ?,
                CURDATE(),
                ?,
                'en_attente'
            )
        ");

        $vente->execute([
            $_SESSION['user']['id_user'],
            $total
        ]);

        $idVente = $bdd->lastInsertId();

        foreach ($panier as $article) {

            $ligne = $bdd->prepare("
                INSERT INTO ligne_vente(
                    id_vente,
                    id_produit,
                    quantite,
                    prix
                )
                VALUES (?, ?, ?, ?)
            ");

            $ligne->execute([
                $idVente,
                $article['id'],
                $article['qty'],
                $article['price']
            ]);

            $stock = $bdd->prepare("
                UPDATE produit
                SET stock = stock - ?
                WHERE id_produit = ?
            ");

            $stock->execute([
                $article['qty'],
                $article['id']
            ]);
        }

        $bdd->commit();

        echo json_encode([
            "success" => true
        ]);

    } catch (Exception $e) {

        $bdd->rollBack();

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }

    exit();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caissier</title>
    <link rel="stylesheet" href="caissier.css">
</head>
<body>
    <header class="header-connected">
        <div class="user-info">
            <div class="profile-badge">
                <div class="profile-icon">👤</div>
                <div class="profile-text">
                    <h3><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></h3>
                    <p>Caissier</p>
                </div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Déconnexion</a>
    </header>
    <main>
        <!-- Ajoutez ici le contenu spécifique au caissier -->
        <div class="container">

            <!-- PRODUITS -->
            <div class="products">
                <h2>Produits</h2>

                <div class="grid">
                    <?php foreach ($produits as $p): ?>
                    <button class="product-btn" data-id="<?= $p['id_produit'] ?>" data-name="<?= $p['nom'] ?>"
                        data-price="<?= $p['prix'] ?>">
                        <span>
                            <?= $p['nom'] ?>
                        </span>
                        <strong>
                            <?= $p['prix'] ?> €
                        </strong>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- PANIER -->
            <div class="cart">
                <h2>Panier</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>Prix</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cart-body"></tbody>
                </table>

                <div class="total-box">
                    <p>
                        Mode de paiement :
                        <select id="payment-method">
                            <option value="espece">Espèces</option>
                            <option value="carte">Carte bancaire</option>
                        </select>
                    </p>
                    <p>Total TTC : <span id="total">0.00</span> €</p>
                    <p>Reçu : <input type="number" id="received" step="0.01"></p>
                    <p>Rendu : <span id="change">0.00</span> €</p>
                </div>

                <div class="actions">
                    <button id="clear">Vider</button>
                    <button id="pay">Payer</button>
                </div>
            </div>

         </div>

    </main>

    <script src="caissier.js" defer></script>

</body>
</html>