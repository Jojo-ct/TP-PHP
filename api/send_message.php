<?php
header('Content-Type: application/json');
require_once 'database.php'; // Inclut votre fichier de connexion à la base de données

// Fonction pour l'upload de fichiers (incluse ici car functions.php n'est pas utilisé)
function uploadFile($file, $upload_dir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Assurez-vous que le répertoire d'upload existe
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = uniqid() . '_' . basename($file['name']);
    $target_file = $upload_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Vérifier si le fichier est une image réelle ou une fausse image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        // Si ce n'est pas une image, vérifiez si c'est une vidéo
        $allowedVideoTypes = ['mp4', 'webm', 'ogg'];
        if (!in_array($imageFileType, $allowedVideoTypes)) {
            error_log("Fichier non image/vidéo ou type non autorisé: " . $file['type']);
            return false;
        }
    }

    // Vérifier la taille du fichier (ex: max 50MB)
    if ($file["size"] > 50000000) { // 50 MB
        error_log("Désolé, votre fichier est trop volumineux.");
        return false;
    }

    // Autoriser certains formats de fichiers
    $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $allowedTypes = array_merge($allowedImageTypes, ['mp4', 'webm', 'ogg']); // Ajouter les types vidéo
    if (!in_array($imageFileType, $allowedTypes)) {
        error_log("Désolé, seuls les fichiers JPG, JPEG, PNG, GIF, MP4, WEBM, OGG sont autorisés.");
        return false;
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Retourne le chemin relatif depuis la racine du projet pour l'accès web
        // Si send_message.php est dans api/ et uploads dans assets/, alors le chemin est ../assets/uploads/
        // Mais l'URL d'accès public sera 'assets/uploads/messages/nom_du_fichier.ext'
        // Nous stockons le chemin relatif à la racine du projet web dans la BDD.
        // Donc, si 'assets' est à la racine, le chemin à stocker est 'assets/uploads/messages/...'
        return 'assets/uploads/messages/' . $file_name;
    } else {
        error_log("Erreur lors du déplacement du fichier uploadé.");
        return false;
    }
}


$data = json_decode(file_get_contents('php://input'), true);

// Si les données ne sont pas au format JSON, elles proviennent d'un FormData (upload de fichier)
if (empty($data)) {
    $sender_id = $_POST['sender_id'] ?? null;
    $receiver_id = $_POST['receiver_id'] ?? null;
    $message_text = $_POST['message_text'] ?? null;
} else {
    $sender_id = $data['sender_id'] ?? null;
    $receiver_id = $data['receiver_id'] ?? null;
    $message_text = $data['message_text'] ?? null;
}


// Gestion de l'upload d'image si présente
$image_url = null;
if (isset($_FILES['message_image']) && $_FILES['message_image']['error'] === UPLOAD_ERR_OK) {
    // Chemin d'upload ajusté pour placer les fichiers dans assets/uploads/messages/
    $upload_dir = '../assets/uploads/messages/'; // Remonte de 'api/', va dans 'assets/', puis 'uploads/messages/'
    $image_url = uploadFile($_FILES['message_image'], $upload_dir);
    if (!$image_url) {
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'upload de l\'image.']);
        exit;
    }
}

if (!$sender_id || !$receiver_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de l\'expéditeur ou du destinataire manquant.']);
    exit;
}

if (!$message_text && !$image_url) {
    echo json_encode(['status' => 'error', 'message' => 'Le message ne peut pas être vide et aucune image n\'a été fournie.']);
    exit;
}

try {
    // Utilisez la variable $pdo directement car elle est définie dans database.php
    // $pdo = get_db_connection(); // Cette ligne est supprimée

    // 1. Rechercher une conversation existante entre les deux utilisateurs
    // L'ordre des user_id n'a pas d'importance grâce à l'UNIQUE constraint (user1_id, user2_id)
    $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    $conversation_id = null;
    if ($conversation) {
        $conversation_id = $conversation['conversation_id'];
    } else {
        // 2. Si aucune conversation n'existe, en créer une nouvelle
        $stmt = $pdo->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
        $stmt->execute([$sender_id, $receiver_id]);
        $conversation_id = $pdo->lastInsertId();
    }

    // 3. Insérer le message dans la table 'messages'
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message_text, image_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$conversation_id, $sender_id, $message_text, $image_url]);

    echo json_encode(['status' => 'success', 'message' => 'Message envoyé avec succès.']);

} catch (PDOException $e) {
    error_log("Erreur de base de données lors de l'envoi de message: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erreur générale lors de l'envoi de message: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue : ' . $e->getMessage()]);
}
?>

