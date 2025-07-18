<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = $data['user_id'] ?? null;
    $parentCommentId = $data['parent_comment_id'] ?? null;
    $replyText = $data['reply_text'] ?? null;

    if (empty($userId) || empty($parentCommentId) || empty($replyText)) {
        $response['message'] = 'Données manquantes (utilisateur, commentaire parent ou texte de réponse).';
        echo json_encode($response);
        exit();
    }

    try {
        // Vérifier si le commentaire parent existe et récupérer son post_id
        $checkStmt = $pdo->prepare("SELECT comment_id, post_id FROM comments WHERE comment_id = ?");
        $checkStmt->bindParam(1, $parentCommentId, PDO::PARAM_INT);
        $checkStmt->execute();
        $parentComment = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$parentComment) {
            $response['message'] = 'Le commentaire parent n\'existe pas.';
            echo json_encode($response);
            exit();
        }

        $postId = $parentComment['post_id']; // Récupérer le post_id du commentaire parent

        // Insérer la réponse dans la table des commentaires
        // Utiliser le post_id récupéré précédemment
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, comment_text, parent_comment_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bindParam(1, $postId, PDO::PARAM_INT); // Utilisation du post_id récupéré
        $stmt->bindParam(2, $userId, PDO::PARAM_INT);
        $stmt->bindParam(3, $replyText, PDO::PARAM_STR);
        $stmt->bindParam(4, $parentCommentId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Réponse ajoutée avec succès.';
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['message'] = 'Erreur lors de l\'enregistrement de la réponse : ' . ($errorInfo[2] ?? 'Erreur inconnue');
        }

    } catch (PDOException $e) {
        error_log("Database error in post_comment_reply.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
