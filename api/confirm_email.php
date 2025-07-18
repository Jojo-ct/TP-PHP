<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À ajuster pour la production

require_once 'database.php';

$token = $_GET['token'] ?? null; // Le jeton est passé via l'URL (GET)

if (!$token) {
    echo json_encode(["success" => false, "message" => "Jeton de confirmation manquant."]);
    exit();
}

try {
    $pdo->beginTransaction();

    // Trouver l'utilisateur avec ce jeton et vérifier l'expiration
    $stmt = $pdo->prepare("SELECT user_id, confirmation_token_expiry FROM users WHERE confirmation_token = ? AND email_confirmed = FALSE");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "Jeton invalide ou déjà utilisé."]);
        $pdo->rollBack();
        exit();
    }

    if (strtotime($user['confirmation_token_expiry']) < time()) {
        echo json_encode(["success" => false, "message" => "Le jeton de confirmation a expiré."]);
        $pdo->rollBack();
        exit();
    }

    // Mettre à jour l'utilisateur comme confirmé et invalider le jeton
    $stmt = $pdo->prepare("UPDATE users SET email_confirmed = TRUE, confirmation_token = NULL, confirmation_token_expiry = NULL WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Votre email a été confirmé avec succès ! Vous pouvez maintenant vous connecter."]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log("Erreur PDO lors de la confirmation d'email: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données lors de la confirmation. Veuillez réessayer plus tard."]);
} catch (Exception $e) {
    error_log("Erreur générale lors de la confirmation d'email: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Une erreur inattendue est survenue : " . $e->getMessage()]);
}
?>
