// Dans script.js ou un fichier common.js
function checkAuth() {
    const userId = sessionStorage.getItem('userId');
    if (!userId) {
        // Pas de user ID trouvé, rediriger vers la page de connexion
        window.location.href = 'login.html';
    }
    // Optionnel: Afficher le nom de l'utilisateur sur la page d'accueil
    const userNameElement = document.getElementById('loggedInUserName'); // Ajoutez un élément HTML avec cet ID
    if (userNameElement) {
        userNameElement.textContent = sessionStorage.getItem('userName');
    }
}

// Appeler cette fonction au chargement de la page si elle est protégée
// Exemple: sur index.html:
// document.addEventListener('DOMContentLoaded', checkAuth);

// Fonction de déconnexion
function logout() {
    sessionStorage.clear(); // Efface toutes les données de session
    // Optionnel: Appeler le backend /backend/logout.php si une logique serveur est nécessaire
    fetch('/backend/logout.php')
        .then(response => response.json())
        .then(data => {
            console.log(data.message);
            window.location.href = 'login.html'; // Rediriger
        })
        .catch(error => {
            console.error('Erreur lors de la déconnexion:', error);
            window.location.href = 'login.html'; // Rediriger même en cas d'erreur
        });
}

// Attacher la fonction logout à un bouton de déconnexion
// Exemple: document.getElementById('logoutButton').addEventListener('click', logout);