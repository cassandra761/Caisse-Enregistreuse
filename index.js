// Gestion de la connexion (vérification serveur via PHP)
document.getElementById('loginForm').addEventListener('submit', function(e) {
    // Le formulaire est soumis en POST vers index.php
    // La vérification des identifiants est effectuée côté serveur dans la base de données
});

// Retour à la page de connexion au chargement
document.addEventListener('DOMContentLoaded', function() { // Lorsque la page est chargée
    // Vérifier si une session existe
    const userSession = localStorage.getItem('userSession'); // Récupération de la session utilisateur depuis le localStorage
    if (userSession) { // Si une session existe
        // L'utilisateur est déjà connecté, le rediriger vers l'espace connecté
        window.location.href = 'expense_report.php'; // Redirection vers la page de l'espace connecté
    }
});
