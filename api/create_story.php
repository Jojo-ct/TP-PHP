<?php
header('Content-Type: application/json');
include 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? null;
    $mediaFile = $_FILES['media'] ?? null;

    if (empty($userId)) {
        $response['message'] = 'ID utilisateur manquant.';
        echo json_encode($response);
        exit();
    }

    if (!$mediaFile || $mediaFile['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Aucun fichier média reçu ou erreur d\'upload.';
        echo json_encode($response);
        exit();
    }

    // Vérifier le type de fichier (image ou vidéo)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm'];
    if (!in_array($mediaFile['type'], $allowedTypes)) {
        $response['message'] = 'Type de fichier non autorisé. Seules les images (JPEG, PNG, GIF) et vidéos (MP4, WebM) sont autorisées.';
        echo json_encode($response);
        exit();
    }

    $uploadDir = '../assets/stories/'; // Dossier où les stories seront stockées
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Créer le dossier s'il n'existe pas
    }

    $fileName = uniqid() . '_' . basename($mediaFile['name']);
    $targetFilePath = $uploadDir . $fileName;
    $dbFilePath = 'assets/stories/' . $fileName; // Chemin à enregistrer en BDD

    if (move_uploaded_file($mediaFile['tmp_name'], $targetFilePath)) {
        try {
            // Calculer la date d'expiration (24 heures après la création)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare("INSERT INTO stories (user_id, media_url, expires_at) VALUES (:user_id, :media_url, :expires_at)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':media_url', $dbFilePath, PDO::PARAM_STR);
            $stmt->bindParam(':expires_at', $expiresAt, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Story ajoutée avec succès.';
            } else {
                $response['message'] = 'Erreur lors de l\'enregistrement de la story en base de données.';
            }
        } catch (PDOException $e) {
            error_log("Database error in create_story.php: " . $e->getMessage());
            $response['message'] = 'Erreur de base de données lors de l\'ajout de la story.';
        }
    } else {
        $response['message'] = 'Erreur lors du déplacement du fichier uploadé.';
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
