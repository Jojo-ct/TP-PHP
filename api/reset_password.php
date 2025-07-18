<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À ajuster pour la production

require_once 'database.php';

// Fonctions utilitaires (intégrées) - Pas strictement nécessaires ici, mais pour la cohérence
function getLoggedInUserId() { return null; /* Non applicable pour la réinitialisation */ }
function checkUserRole($pdo, $userId, $requiredRole) { return true; /* Non applicable pour la réinitialisation */ }


$data = json_decode(file_get_contents('php://input'), true);

$token = trim($data['token'] ?? '');
$new_password = $data['new_password'] ?? '';

if (empty($token) || empty($new_password)) {
    echo json_encode(["success" => false, "message" => "Jeton ou nouveau mot de passe manquant."]);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(["success" => false, "message" => "Le nouveau mot de passe doit contenir au moins 6 caractères."]);
    exit();
}

try {
    $pdo->beginTransaction();
    $userFound = false;
    $userId = null;
    $email = '';
    $tokenExpiry = null;

    // Rechercher le jeton dans la table users (tous rôles confondus)
    $stmt = $pdo->prepare("SELECT user_id, email, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userId = $user['user_id'];
        $email = $user['email'];
        $tokenExpiry = $user['reset_token_expiry'];
        $userFound = true;
    }

    if (!$userFound) {
        echo json_encode(["success" => false, "message" => "Jeton de réinitialisation invalide ou expiré."]);
        $pdo->rollBack();
        exit();
    }

    if (strtotime($tokenExpiry) < time()) {
        echo json_encode(["success" => false, "message" => "Le jeton de réinitialisation a expiré."]);
        $pdo->rollBack();
        exit();
    }

    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Mettre à jour le mot de passe et invalider le jeton dans la table 'users'
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
    $updateStmt->execute([$new_password_hash, $userId]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Votre mot de passe a été réinitialisé avec succès !"]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log("Erreur PDO lors de la réinitialisation du mot de passe: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données. Veuillez réessayer plus tard."]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erreur générale lors de la réinitialisation du mot de passe: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Une erreur inattendue est survenue : " . $e->getMessage()]);
}
?>
