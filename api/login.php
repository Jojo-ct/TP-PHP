<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À ajuster pour la production pour des raisons de sécurité

require_once 'database.php'; // Inclut la connexion PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(["success" => false, "message" => "Veuillez entrer votre email et mot de passe."]);
        exit();
    }

    try {
        // Tenter la connexion en recherchant l'utilisateur dans la table 'users'
        // CORRECTION ICI : Utilisation de 'prenom' et 'nom'
        $stmt = $pdo->prepare("SELECT user_id, prenom, nom, email, password_hash, email_confirmed, profile_picture_url, role, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['email_confirmed']) {
                echo json_encode(["success" => false, "message" => "Votre email n'a pas encore été confirmé. Veuillez vérifier votre boîte de réception."]);
                exit();
            }
            if ($user['status'] === 'blocked') {
                echo json_encode(["success" => false, "message" => "Votre compte est bloqué. Veuillez contacter l'administrateur."]);
                exit();
            }

            // Mettre à jour la dernière connexion (facultatif)
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);

            // Retourner les informations de l'utilisateur, y compris le rôle
            // CORRECTION ICI : Utilisation de 'prenom' et 'nom' pour la réponse JSON
            echo json_encode([
                "success" => true,
                "message" => "Connexion réussie !",
                "token" => bin2hex(random_bytes(32)), // Génère un token aléatoire (pourrait être un JWT)
                "user" => [
                    "user_id" => $user['user_id'],
                    "first_name" => $user['prenom'], // Utilise 'prenom' de la base de données
                    "last_name" => $user['nom'],     // Utilise 'nom' de la base de données
                    "email" => $user['email'],
                    "profile_picture_url" => $user['profile_picture_url'],
                    "role" => $user['role'] // Indique le rôle de l'utilisateur connecté
                ]
            ]);

        } else {
            echo json_encode(["success" => false, "message" => "Email ou mot de passe incorrect."]);
        }

    } catch (\PDOException $e) {
        error_log("Erreur PDO lors de la connexion: " . $e->getMessage());
        // Pour le débogage, afficher temporairement le message d'erreur PDO complet
        echo json_encode(["success" => false, "message" => "Erreur de base de données : " . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Erreur générale lors de la connexion: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Une erreur inattendue est survenue : " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Méthode de requête non autorisée."]);
}
?>
