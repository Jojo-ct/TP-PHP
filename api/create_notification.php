<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $recipientId = $data['recipient_id'] ?? null;
    $senderId = $data['sender_id'] ?? null; // Peut être null si la notification n'a pas d'expéditeur direct (ex: système)
    $type = $data['type'] ?? null; // Ex: 'friend_request', 'friend_accepted', 'new_message', 'post_reaction', 'post_comment'
    $content = $data['content'] ?? null;

    if (empty($recipientId) || empty($type) || empty($content)) {
        $response['message'] = 'Données de notification manquantes (destinataire, type ou contenu).';
        echo json_encode($response);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, type, content, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->bindParam(1, $recipientId, PDO::PARAM_INT);
        $stmt->bindParam(2, $senderId, PDO::PARAM_INT); // PDO::PARAM_NULL si $senderId est null
        $stmt->bindParam(3, $type, PDO::PARAM_STR);
        $stmt->bindParam(4, $content, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Notification créée avec succès.';
        } else {
            $response['message'] = 'Erreur lors de l\'enregistrement de la notification.';
        }

    } catch (PDOException $e) {
        error_log("Database error in create_notification.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
