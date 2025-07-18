<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À ajuster pour la production

require_once 'database.php';
require_once 'send_email.php'; // Inclut la fonction d'envoi d'email

// Fonctions utilitaires (intégrées) - Pas strictement nécessaires ici, mais pour la cohérence
function getLoggedInUserId() { return null; /* Non applicable pour l'inscription */ }
function checkUserRole($pdo, $userId, $requiredRole) { return true; /* Non applicable pour l'inscription */ }


$data = json_decode(file_get_contents('php://input'), true);

$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Tous les champs sont requis."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Format d'email invalide."]);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(["success" => false, "message" => "Le mot de passe doit contenir au moins 6 caractères."]);
    exit();
}

try {
    // Vérifier si l'email existe déjà dans la table users (tous rôles confondus)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(["success" => false, "message" => "Cet email est déjà enregistré."]);
        exit();
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $confirmation_token = bin2hex(random_bytes(32));
    $confirmation_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Jeton valide 1 heure

    // Insérer le nouvel utilisateur avec le rôle 'client' et email_confirmed à FALSE par défaut
    $stmt = $pdo->prepare("INSERT INTO users (prenom, nom, email, password_hash, role, email_confirmed, confirmation_token, confirmation_token_expiry, status) VALUES (?, ?, ?, ?, 'client', FALSE, ?, ?, 'active')");
    $stmt->execute([$first_name, $last_name, $email, $password_hash, $confirmation_token, $confirmation_token_expiry]);

    // Envoyer l'email de confirmation
    $confirmation_link = 'http://localhost/reseau/vues/clients/affichageposts.html?action=confirm_email&token=' . $confirmation_token; // Ajustez ce chemin si nécessaire
    $email_template = file_get_contents('../vues/clients/email_templates/confirmation_email.html'); // Chemin vers le template HTML
    $email_body = str_replace(
        ['{{user_name}}', '{{confirmation_link}}'],
        [$first_name . ' ' . $last_name, $confirmation_link],
        $email_template
    );

    if (sendEmail($email, $first_name . ' ' . $last_name, 'Confirmez votre adresse email', $email_body)) {
        echo json_encode(["success" => true, "message" => "Inscription réussie ! Un email de confirmation a été envoyé à votre adresse."]);
    } else {
        error_log("Erreur lors de l'envoi de l'email de confirmation pour " . $email);
        echo json_encode(["success" => true, "message" => "Inscription réussie, mais l'envoi de l'email de confirmation a échoué. Veuillez contacter le support."]);
    }

} catch (\PDOException $e) {
    error_log("Erreur PDO lors de l'inscription: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données lors de l'inscription. Veuillez réessayer plus tard."]);
} catch (Exception $e) {
    error_log("Erreur générale lors de l'inscription: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Une erreur inattendue est survenue : " . $e->getMessage()]);
}
?>
