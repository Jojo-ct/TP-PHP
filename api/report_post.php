<?php
header('Content-Type: application/json');
require_once 'database.php'; // Inclut la connexion PDO

// Fonctions utilitaires (intégrées)
// getLoggedInUserId est nécessaire pour récupérer l'ID de l'utilisateur qui signale
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

// checkUserRole n'est pas strictement nécessaire ici car tout utilisateur connecté peut signaler,
// mais je l'inclus pour la cohérence si vous décidez d'ajouter des restrictions.
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
$reporterUserId = $input['reporter_user_id'] ?? null;
$reason = $input['reason'] ?? null; // La raison est facultative

// Vérification minimale des données
if (!$postId || !$reporterUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du post ou de l\'utilisateur signalant manquant.']);
    exit();
}

try {
    // Vérifier si le post existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE post_id = ?");
    $stmt->execute([$postId]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post introuvable.']);
        exit();
    }

    // Vérifier si l'utilisateur signalant existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
    $stmt->execute([$reporterUserId]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur signalant introuvable.']);
        exit();
    }

    // Vérifier si ce post a déjà été signalé par cet utilisateur
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_reports WHERE post_id = ? AND reporter_user_id = ?");
    $stmt->execute([$postId, $reporterUserId]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflit
        echo json_encode(['success' => false, 'message' => 'Vous avez déjà signalé cette publication.']);
        exit();
    }

    // Insérer le signalement dans la table post_reports
    $stmt = $pdo->prepare("INSERT INTO post_reports (post_id, reporter_user_id, reason) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $reporterUserId, $reason]);

    echo json_encode(['success' => true, 'message' => 'Publication signalée avec succès.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors du signalement du post: ' . $e->getMessage()]);
}
?>
