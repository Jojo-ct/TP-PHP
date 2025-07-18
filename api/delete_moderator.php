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

// Récupérer l'ID utilisateur qui effectue l'action (l'admin)
$adminUserId = getLoggedInUserId();
checkUserRole($pdo, $adminUserId, 'admin'); // Seul un admin peut supprimer des modérateurs

$moderatorId = $input['moderator_id'] ?? null;

if (!$moderatorId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du modérateur manquant.']);
    exit();
}

try {
    // Vérifier si l'utilisateur à supprimer est bien un modérateur
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$moderatorId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'moderator') {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Modérateur introuvable ou rôle incorrect.']);
        exit();
    }

    // Supprimer le modérateur
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'moderator'");
    $stmt->execute([$moderatorId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Modérateur supprimé avec succès.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Échec de la suppression du modérateur.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la suppression du modérateur: ' . $e->getMessage()]);
}
?>
