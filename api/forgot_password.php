<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À ajuster pour la production

require_once 'database.php';
require_once 'send_email.php'; // Inclut la fonction d'envoi d'email

// Fonctions utilitaires (intégrées) - Pas strictement nécessaires ici, mais pour la cohérence
function getLoggedInUserId() { return null; /* Non applicable pour la réinitialisation */ }
function checkUserRole($pdo, $userId, $requiredRole) { return true; /* Non applicable pour la réinitialisation */ }


$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(["success" => false, "message" => "Veuillez entrer votre email."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Format d'email invalide."]);
    exit();
}

try {
    $pdo->beginTransaction();
    $userFound = false;
    $userId = null;
    $userName = '';

    // Rechercher l'utilisateur dans la table 'users' (tous rôles confondus)
    $stmt = $pdo->prepare("SELECT user_id, prenom, nom FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userId = $user['user_id'];
        $userName = $user['prenom'] . ' ' . $user['nom'];
        $userFound = true;
    }

    if ($userFound) {
        $reset_token = bin2hex(random_bytes(32));
        $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+30 minutes')); // Jeton valide 30 minutes

        // Mettre à jour le jeton de réinitialisation dans la table 'users'
        $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
        $updateStmt->execute([$reset_token, $reset_token_expiry, $userId]);

        // Envoyer l'email de réinitialisation
        $reset_link = 'http://localhost/reseau/vues/clients/affichageposts.html?action=reset_password&token=' . $reset_token; // Ajustez ce chemin
        $email_template = file_get_contents('../vues/clients/email_templates/reset_password_email.html'); // Chemin vers le template HTML
        $email_body = str_replace(
            ['{{user_name}}', '{{reset_link}}', '{{expiry_minutes}}'],
            [$userName, $reset_link, '30'],
            $email_template
        );

        if (sendEmail($email, $userName, 'Réinitialisation de votre mot de passe', $email_body)) {
            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Si un compte avec cet email existe, un lien de réinitialisation a été envoyé."]);
        } else {
            $pdo->rollBack();
            error_log("Erreur lors de l'envoi de l'email de réinitialisation pour " . $email);
            echo json_encode(["success" => false, "message" => "Erreur lors de l'envoi de l'email de réinitialisation. Veuillez réessayer plus tard."]);
        }
    } else {
        // Pour des raisons de sécurité, toujours renvoyer un message générique
        $pdo->commit(); // Commit pour ne pas laisser de transaction ouverte si aucun utilisateur n'est trouvé
        echo json_encode(["success" => true, "message" => "Si un compte avec cet email existe, un lien de réinitialisation a été envoyé."]);
    }

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log("Erreur PDO lors de la demande de mot de passe oublié: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données. Veuillez réessayer plus tard."]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erreur générale lors de la demande de mot de passe oublié: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Une erreur inattendue est survenue : " . $e->getMessage()]);
}
?>

