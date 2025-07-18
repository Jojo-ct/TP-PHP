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

$postId = $input['post_id'] ?? null;
$currentUserId = getLoggedInUserId(); // L'ID de l'utilisateur qui demande les détails

if (!$postId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du post manquant.']);
    exit();
}

// Seuls les modérateurs et les administrateurs peuvent voir les détails des posts signalés
// Si cette API est utilisée pour d'autres cas, ajustez la vérification de rôle.
checkUserRole($pdo, $currentUserId, 'moderator'); 

try {
    // Récupérer les détails du post
    $stmt = $pdo->prepare("
        SELECT p.post_id, p.description, p.image_url, p.created_at,
               u.user_id, u.prenom , u.nom , u.profile_picture_url
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post introuvable.']);
        exit();
    }

    // Récupérer les signalements associés à ce post
    $stmtReports = $pdo->prepare("
        SELECT pr.report_id, pr.reason, pr.reported_at,
               u.prenom AS reporter_first_name, u.nom AS reporter_last_name
        FROM post_reports pr
        JOIN users u ON pr.reporter_user_id = u.user_id
        WHERE pr.post_id = ?
        ORDER BY pr.reported_at DESC
    ");
    $stmtReports->execute([$postId]);
    $reports = $stmtReports->fetchAll(PDO::FETCH_ASSOC);

    $post['reports'] = $reports;

    echo json_encode(['success' => true, 'post' => $post]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la récupération des détails du post: ' . $e->getMessage()]);
}
?>
