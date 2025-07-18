<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À restreindre en production
require_once 'database.php'; // Assurez-vous que ce chemin est correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $postId = $data['post_id'] ?? null;
    $commentText = $data['comment_text'] ?? null;
    $userId = $data['user_id'] ?? null; // L'ID de l'utilisateur qui commente

    if (empty($postId) || empty($commentText) || empty($userId)) {
        echo json_encode(["success" => false, "message" => "Données manquantes (post_id, comment, ou user_id)."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO Comments (post_id, user_id, comment_text) VALUES (:post_id, :user_id, :comment_text)");
        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':comment_text' => $commentText
        ]);

        echo json_encode(["success" => true, "message" => "Commentaire ajouté avec succès."]);

    } catch (PDOException $e) {
        error_log("Erreur PDO dans post_comment.php: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Erreur de base de données lors de l'ajout du commentaire."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Méthode de requête non autorisée."]);
}
?>