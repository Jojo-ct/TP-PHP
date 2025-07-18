<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['status' => 'error', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $senderId = $data['sender_id'] ?? null;
    $receiverId = $data['receiver_id'] ?? null; // Toujours n\u00e9cessaire pour trouver/cr\u00e9er la conversation
    $messageText = $data['message_text'] ?? '';
    $storyId = $data['story_id'] ?? null;

    if (empty($senderId) || empty($receiverId)) {
        $response['message'] = 'ID de l\'exp\u00e9diteur ou du destinataire manquant.';
        echo json_encode($response);
        exit();
    }

    if (empty($messageText) && empty($storyId)) {
        $response['message'] = 'Message vide.';
        echo json_encode($response);
        exit();
    }

    try {
        // V\u00e9rifier si une conversation existe d\u00e9j\u00e0 entre ces deux utilisateurs
        $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
        $stmt->bindParam(1, $senderId, PDO::PARAM_INT);
        $stmt->bindParam(2, $receiverId, PDO::PARAM_INT);
        $stmt->bindParam(3, $receiverId, PDO::PARAM_INT);
        $stmt->bindParam(4, $senderId, PDO::PARAM_INT);
        $stmt->execute();
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        $conversationId = null;
        if ($conversation) {
            $conversationId = $conversation['conversation_id'];
        } else {
            // Cr\u00e9er une nouvelle conversation si elle n'existe pas
            // Initialiser last_message_at et last_message_text ici
            $stmt = $pdo->prepare("INSERT INTO conversations (user1_id, user2_id, last_message_at, last_message_text) VALUES (?, ?, NOW(), ?)");
            $stmt->bindParam(1, $senderId, PDO::PARAM_INT);
            $stmt->bindParam(2, $receiverId, PDO::PARAM_INT);
            $stmt->bindParam(3, $messageText, PDO::PARAM_STR); // Utilisation de messageText pour last_message_text
            $stmt->execute();
            $conversationId = $pdo->lastInsertId();
        }

        // Ins\u00e9rer le message dans la table messages (sans receiver_id)
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message_text, story_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bindParam(1, $conversationId, PDO::PARAM_INT);
        $stmt->bindParam(2, $senderId, PDO::PARAM_INT);
        $stmt->bindParam(3, $messageText, PDO::PARAM_STR);
        
        // G\u00e9rer le story_id: PDO::PARAM_INT si non null, PDO::PARAM_NULL si null
        if ($storyId === null) {
            $stmt->bindValue(4, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(4, $storyId, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            // Mettre \u00e0 jour last_message_at dans la conversation
            $updateConvStmt = $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), last_message_text = ? WHERE conversation_id = ?");
            $updateConvStmt->bindParam(1, $messageText, PDO::PARAM_STR);
            $updateConvStmt->bindParam(2, $conversationId, PDO::PARAM_INT);
            $updateConvStmt->execute();

            $response['status'] = 'success';
            $response['message'] = 'Message envoy\u00e9 avec succ\u00e8s.';
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['message'] = 'Erreur lors de l\'envoi de la r\u00e9ponse : ' . ($errorInfo[2] ?? 'Erreur inconnue');
        }

    } catch (PDOException $e) {
        error_log("Database error in send_story_reply.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de donn\u00e9es : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'M\u00e9thode de requ\u00eate non autoris\u00e9e.';
}

echo json_encode($response);
?>
