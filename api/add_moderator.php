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
checkUserRole($pdo, $adminUserId, 'admin'); // Seul un admin peut ajouter des modérateurs

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$firstName = $input['first_name'] ?? '';
$lastName = $input['last_name'] ?? '';

if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
    exit();
}

// Validation basique de l'email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format d\'email invalide.']);
    exit();
}

try {
    // Vérifier si l'email existe déjà dans la table users
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflit
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé.']);
        exit();
    }

    // Hacher le mot de passe
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insérer le nouvel utilisateur avec le rôle 'moderator'
    $stmt = $pdo->prepare("INSERT INTO users (prenom, nom, email, password_hash, role, email_confirmed, status) VALUES (?, ?, ?, ?, 'moderator', TRUE, 'active')");
    $stmt->execute([$firstName, $lastName, $email, $passwordHash]);

    echo json_encode(['success' => true, 'message' => 'Modérateur ajouté avec succès.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de l\'ajout du modérateur: ' . $e->getMessage()]);
}
?>
