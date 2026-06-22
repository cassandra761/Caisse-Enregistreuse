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