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

// Récupérer l'ID utilisateur à partir de la requête (simulé pour l'exemple)
$userId = getLoggedInUserId();

// Vérifier si l'utilisateur est un administrateur
checkUserRole($pdo, $userId, 'admin');

try {
    // Compter le total des utilisateurs (actifs ou bloqués)
    $stmtTotalUsers = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
    $totalUsers = $stmtTotalUsers->fetchColumn();

    // Compter le total des posts
    $stmtTotalPosts = $pdo->query("SELECT COUNT(*) AS total_posts FROM posts");
    $totalPosts = $stmtTotalPosts->fetchColumn();

    // Compter le total des modérateurs
    $stmtTotalModerators = $pdo->query("SELECT COUNT(*) AS total_moderators FROM users WHERE role = 'moderator'");
    $totalModerators = $stmtTotalModerators->fetchColumn();

    echo json_encode([
        'success' => true,
        'total_users' => $totalUsers,
        'total_posts' => $totalPosts,
        'total_moderators' => $totalModerators
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la récupération des statistiques admin: ' . $e->getMessage()]);
}
?>
