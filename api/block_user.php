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
$currentUserRole = checkUserRole($pdo, $currentUserId, 'moderator');

$userIdToBlock = $input['target_user_id'] ?? null; // Nom de la variable corrigé pour correspondre au frontend

if (!$userIdToBlock) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de l\'utilisateur à bloquer manquant.']);
    exit();
}

// Empêcher un utilisateur de se bloquer lui-même
if ((int)$currentUserId === (int)$userIdToBlock) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas vous bloquer vous-même.']);
    exit();
}

try {
    // Vérifier le rôle de l'utilisateur à bloquer
    $stmtTargetUser = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmtTargetUser->execute([$userIdToBlock]);
    $targetUser = $stmtTargetUser->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur cible introuvable.']);
        exit();
    }

    // Un modérateur ne peut pas bloquer un administrateur
    if ($currentUserRole === 'moderator' && $targetUser['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Un modérateur ne peut pas bloquer un administrateur.']);
        exit();
    }

    // Mettre à jour le statut de l'utilisateur à 'blocked'
    $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE user_id = ?");
    $stmt->execute([$userIdToBlock]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Utilisateur bloqué avec succès.']);
    } else {
        http_response_code(400); // Bad Request si l'utilisateur est déjà bloqué ou n'existe pas
        echo json_encode(['success' => false, 'message' => 'Échec du blocage de l\'utilisateur (peut-être déjà bloqué ou introuvable).']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors du blocage de l\'utilisateur: ' . $e->getMessage()]);
}
?>


