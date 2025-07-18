<?php
header('Content-Type: application/json');
ini_set('display_errors', 1); // Laissez ceci pour le débogage si besoin, retirez en production
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'database.php'; // Adaptez le chemin si nécessaire

$uploadProfilePicDir = '../assets/images/profile_pictures/';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? null;
    $firstName = $_POST['first_name'] ?? null;
    $lastName = $_POST['last_name'] ?? null;
    // Gestion du mot de passe
    $newPassword = $_POST['password'] ?? null;
    $passwordHash = null;
    if (!empty($newPassword)) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    // Récupérer l'URL de l'image de profil actuelle depuis la DB si pas de nouvelle image
    // C'est important pour ne pas écraser par NULL si l'utilisateur ne télécharge pas de nouvelle image
    $profilePictureUrl = null; 

    // Validation des données
    if (empty($userId) || empty($firstName) || empty($lastName)) {
        $response['message'] = 'Données manquantes (ID utilisateur, prénom ou nom).';
        echo json_encode($response);
        exit();
    }

    // Gérer le téléchargement de la nouvelle photo de profil
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $destPath = $uploadProfilePicDir . $newFileName;

        if (!is_dir($uploadProfilePicDir)) {
            if (!mkdir($uploadProfilePicDir, 0777, true)) {
                $response['message'] = 'Impossible de créer le répertoire des photos de profil.';
                echo json_encode($response);
                exit();
            }
        }

        $allowedfileExtensions = ['jpg', 'gif', 'png', 'jpeg'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profilePictureUrl = 'assets/images/profile_pictures/' . $newFileName;
            } else {
                $response['message'] = 'Erreur lors du déplacement de la photo de profil.';
                echo json_encode($response);
                exit();
            }
        } else {
            $response['message'] = 'Type de fichier non autorisé pour la photo de profil. Seuls JPG, JPEG, PNG, GIF sont permis.';
            echo json_encode($response);
            exit();
        }
    } else {
        // Si aucune nouvelle image n'est téléchargée, on doit récupérer l'URL de l'image existante
        // pour ne pas la mettre à NULL.
        try {
            $stmt = $pdo->prepare("SELECT profile_picture_url FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $currentProfile = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($currentProfile) {
                $profilePictureUrl = $currentProfile['profile_picture_url'];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de l'URL de l'image de profil actuelle: " . $e->getMessage());
            // Continuer quand même, $profilePictureUrl sera null si échec
        }
    }


    // Préparer et exécuter la mise à jour dans la base de données
    try {
        global $pdo;
        // Construire la requête SQL dynamiquement pour inclure le mot de passe si présent
        $sql = "UPDATE users SET prenom = :first_name, nom = :last_name, profile_picture_url = :profile_picture_url";
        if ($passwordHash) {
            $sql .= ", password_hash = :password_hash";
        }
        $sql .= " WHERE user_id = :user_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':first_name', $firstName, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $lastName, PDO::PARAM_STR);
        $stmt->bindParam(':profile_picture_url', $profilePictureUrl, PDO::PARAM_STR);
        if ($passwordHash) {
            $stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
        }
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Profil mis à jour avec succès !';
        } else {
            $response['message'] = 'Erreur lors de la mise à jour du profil dans la base de données.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
        error_log("Erreur PDO dans update_profile.php: " . $e->getMessage());
    }

} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>