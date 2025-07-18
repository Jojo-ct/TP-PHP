<?php
header('Content-Type: application/json');

// Inclure le fichier de connexion à la base de données.
include 'database.php'; 

// Définir le répertoire d'upload des images.
$uploadFileDir = '../assets/images/posts/';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer l'ID de l'utilisateur et la description du post
    $userId = $_POST['user_id'] ?? null;
    $description = $_POST['description'] ?? '';
    $imageUrl = null; // Sera mis à jour si une image est téléchargée

    // Validation de base : l'ID utilisateur doit être présent
    if (empty($userId)) {
        $response['message'] = 'ID utilisateur manquant. Veuillez vous connecter.';
        echo json_encode($response);
        exit();
    }

    // Gérer le téléchargement de l'image si elle est présente
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name']; // Chemin temporaire du fichier sur le serveur
        $fileName = $_FILES['image']['name'];       // Nom original du fichier
        $fileSize = $_FILES['image']['size'];       // Taille du fichier
        $fileType = $_FILES['image']['type'];       // Type MIME du fichier

        // Extraire l'extension du fichier
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Générer un nom de fichier unique pour éviter les conflits
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        // Chemin complet où le fichier sera sauvegardé sur le serveur
        $destPath = $uploadFileDir . $newFileName;

        // Assurez-vous que le répertoire de destination existe. Si non, essayez de le créer.
        if (!is_dir($uploadFileDir)) {
            // Le 0777 donne toutes les permissions, à changer pour 0755 ou 0775 en production
            if (!mkdir($uploadFileDir, 0777, true)) { 
                $response['message'] = 'Impossible de créer le répertoire de téléchargement.';
                echo json_encode($response);
                exit();
            }
        }

        // Définir les extensions de fichiers autorisées
        $allowedfileExtensions = ['jpg', 'gif', 'png', 'jpeg'];

        // Vérifier l'extension du fichier
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Déplacer le fichier temporaire vers son emplacement final
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // L'URL de l'image pour la base de données et l'affichage web
                // Elle doit être relative au document root de votre serveur web
                // Par exemple, si votre projet est dans htdocs/reseau/, l'URL sera /reseau/assets/images/posts/nom_fichier.jpg
                // Nous stockons juste le chemin relatif au dossier 'reseau' pour la flexibilité
                $imageUrl = 'reseau/assets/images/posts/' . $newFileName; 
            } else {
                $response['message'] = 'Erreur lors du déplacement du fichier téléchargé.';
                echo json_encode($response);
                exit();
            }
        } else {
            $response['message'] = 'Type de fichier image non autorisé. Seuls JPG, JPEG, PNG, GIF sont permis.';
            echo json_encode($response);
            exit();
        }
    }

    // Si ni description ni image n'est fournie, c'est une erreur
    if (empty($description) && empty($imageUrl)) {
        $response['message'] = 'Veuillez fournir une description ou une image pour votre publication.';
        echo json_encode($response);
        exit();
    }

    // Préparer et exécuter l'insertion du post dans la base de données
    try {
        // ICI : Nous utilisons directement la variable $pdo qui est définie par database.php lors de son inclusion.
        // PAS BESOIN d'appeler getDbConnection()
        
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, description, image_url, created_at) VALUES (:user_id, :description, :image_url, NOW())");
        
        // Lier les paramètres à la requête préparée
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        // Utiliser PDO::PARAM_STR pour l'URL de l'image, même si elle est NULL
        $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR); 

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Publication ajoutée avec succès !';
        } else {
            $response['message'] = 'Erreur lors de l\'insertion dans la base de données.';
        }
    } catch (PDOException $e) {
        // Capturer les erreurs de base de données
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
        error_log("Erreur PDO dans create_post.php: " . $e->getMessage()); // Enregistrer l'erreur pour le débogage
    }

} else {
    // Si la méthode de requête n'est pas POST
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
