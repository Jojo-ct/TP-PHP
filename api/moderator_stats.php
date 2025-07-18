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

// Vérifier si l'utilisateur est un modérateur ou un administrateur
checkUserRole($pdo, $userId, 'moderator');

try {
    // Compter le total des posts signalés
    $stmtReportedPosts = $pdo->query("SELECT COUNT(DISTINCT post_id) AS reported_posts FROM post_reports");
    $reportedPosts = $stmtReportedPosts->fetchColumn();

    // Compter le total des utilisateurs bloqués
    $stmtBlockedUsers = $pdo->query("SELECT COUNT(*) AS blocked_users FROM users WHERE status = 'blocked'");
    $blockedUsers = $stmtBlockedUsers->fetchColumn();

    echo json_encode([
        'success' => true,
        'reported_posts' => $reportedPosts,
        'blocked_users' => $blockedUsers
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la récupération des statistiques modérateur: ' . $e->getMessage()]);
}
?>
