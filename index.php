<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Connexion à la base de données
    try {
        $bdd = new PDO(
            'mysql:host=localhost;dbname=caisse;charset=utf8', 'root', '');
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch (PDOException $e) {
        $_SESSION['error_message'] = 'Erreur de connexion à la base de données.';
        header('Location: index.php');
        exit();
    }

    $authenticated = false;
    $userRole = '';
    $userData = [];

    // Vérifier dans la table Caisse
    if (!$authenticated) {
        $stmt = $bdd->prepare('SELECT id_user, nom, prenom, login, mdp FROM Caisse WHERE login = :login');
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['mdp'] === $password) {
            $authenticated = true;
            $userRole = 'caisse';
            $userData = $user;
        }
    }

    // Vérifier dans la table Comptable
    if (!$authenticated) {
        $stmt = $bdd->prepare('SELECT id_user, nom, prenom, login, mdp FROM Comptable WHERE login = :login');
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['mdp'] === $password) {
            $authenticated = true;
            $userRole = 'comptable';
            $userData = $user;
        }
    }

    // Vérifier dans la table Administrateur
    if (!$authenticated) {
        $stmt = $bdd->prepare('SELECT id_user, nom, prenom, login, mdp FROM Administrateur WHERE login = :login');
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['mdp'] === $password) {
            $authenticated = true;
            $userRole = 'administrateur';
            $userData = $user;
        }
    }

    // Si authentifié, créer la session
    if ($authenticated) {
        $_SESSION['user'] = [
            'id_user' => $userData['id_user'],
            'login'   => $userData['login'],
            'role'    => $userRole,
            'nom'     => $userData['nom'],
            'prenom'  => $userData['prenom'],
        ];

        if ($userRole === 'caisse') {
            header('Location: caissier.php');
        } elseif ($userRole === 'comptable') {
            header('Location: comptable.php');
        } elseif ($userRole === 'administrateur') {
            header('Location: admin.php');
        }

        exit();
    } else {
        $_SESSION['error_message'] = 'Nom d\'utilisateur ou mot de passe incorrect.';
        header('Location: index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse Enregistreuse</title>
    <link rel="stylesheet" href="index.css">
    <link rel="icon" href="Acutis-LYCEE.png">
</head>

<body>
    <main class="main">
        <div class="login-container">
            <div class="photo-card">
                <img src="Acutis-LYCEE.png" alt="logo acutis" class="login-photo" width="auto" height="auto">
            </div>
            <div class="login-card">
                <div class="login-content">
                    <div class="login-header">
                        <h1>Connexion</h1>
                        <p>Accédez à votre espace</p>
                    </div>

                    <?php
                    if (isset($_SESSION['error_message'])) {
                        echo '<div id="errorMessage" class="error-message" style="display:block; margin-bottom: 10px; color:#900; background:#fee; border:1px solid #f99; padding:8px;">'.htmlspecialchars($_SESSION['error_message']).'</div>';
                        unset($_SESSION['error_message']);
                    }
                    if (isset($_GET['error'])) {
                        echo '<div id="errorMessage" class="error-message" style="display:block; margin-bottom: 10px; color:#900; background:#fee; border:1px solid #f99; padding:8px;">'.htmlspecialchars($_GET['error']).'</div>';
                    }
                    ?>
                    <form id="loginForm" class="login-form" method="POST" action="index.php">
                        <div class="form-group">
                            <label for="username">
                                Nom d'utilisateur
                            </label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="ex: lvillachane"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">
                                Mot de passe
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password"
                                placeholder="ex: jux7g"
                                required
                            >
                        </div>

                        <button type="submit" class="login-btn">Se connecter</button>
                    </form>

                    <div class="login-footer">
                        <a href="#" class="forgot-password">Mot de passe oublié ?</a>
                        <p style="font-size: 12px; color: var(--gray); margin-top: 15px;">
                            Compte de test : Caissier<br>
                            Login: <strong>jbon</strong><br>
                            MDP: <strong>password</strong>
                        <br>
                            Compte de test : Comptable<br>
                            Login: <strong>ppetit</strong><br>
                            MDP: <strong>password</strong>
                        <br>
                            Compte de test : admin<br>
                            Login: <strong>aterrieur</strong><br>
                            MDP: <strong>password</strong>
                        </p>

                        <p>
                            Pour créer un compte, veuillez contacter l'administrateur du système. <br>
                            * Les données de connexion sont fictives et utilisées uniquement à des fins de démonstration.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>