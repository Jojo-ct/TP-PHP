<?php
header('Content-Type: application/json');
require_once 'database.php'; // Inclut la connexion PDO

// Fonctions utilitaires (intégrées)
function getLoggedInUserId() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['user_id'])) {
        return $input['user_id'];
    }
    if (isset($_GET['user_id'])) {
        return $_GET['user_id'];
    }
    if (isset($_POST['user_id'])) {
        return $_POST['user_id'];
    }
    return null;
}

function checkUserRole($pdo, $userId, $requiredRole) {
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Utilisateur non authentifié.']);
        exit();
    }
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable.']);
        exit();
    }
    $userRole = $user['role'];
    $rolesHierarchy = ['client' => 0, 'moderator' => 1, 'admin' => 2];
    if (!isset($rolesHierarchy[$userRole]) || !isset($rolesHierarchy[$requiredRole])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Rôle invalide configuré.']);
        exit();
    }
    if ($rolesHierarchy[$userRole] < $rolesHierarchy[$requiredRole]) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Accès refusé. Rôle insuffisant.']);
        exit();
    }
    return $userRole;
}

$input = json_decode(file_get_contents('php://input'), true);

// Récupérer l'ID utilisateur qui effectue l'action
$currentUserId = getLoggedInUserId();
// Vérifier si l'utilisateur est un modérateur ou un administrateur
checkUserRole($pdo, $currentUserId, 'moderator');

$postId = $input['post_id'] ?? null;

if (!$postId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du post manquant.']);
    exit();
}

try {
    // Récupérer le chemin de l'image du post avant de le supprimer
    $stmt = $pdo->prepare("SELECT image_url FROM posts WHERE post_id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    $imageUrl = $post['image_url'] ?? null;

    // Supprimer le post et toutes les données associées (commentaires, réactions, signalements)
    // Grâce à ON DELETE CASCADE sur les clés étrangères, la suppression du post
    // dans la table 'posts' devrait automatiquement supprimer les entrées liées
    // dans 'comments', 'reactions', et 'post_reports'.
    $stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = ?");
    $stmt->execute([$postId]);

    if ($stmt->rowCount() > 0) {
        // Si une image était associée, la supprimer du système de fichiers
        // Le chemin doit être relatif à la racine du projet, pas au dossier api/
        if ($imageUrl && file_exists(__DIR__ . '/../' . $imageUrl)) {
            unlink(__DIR__ . '/../' . $imageUrl);
        }
        echo json_encode(['success' => true, 'message' => 'Post supprimé avec succès.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post introuvable ou déjà supprimé.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la suppression du post: ' . $e->getMessage()]);
}
?>
