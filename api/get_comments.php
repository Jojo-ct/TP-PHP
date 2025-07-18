<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => '', 'comments' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $postId = $data['post_id'] ?? null;
    $userId = $data['user_id'] ?? null; // L'ID de l'utilisateur connecté pour récupérer sa réaction

    if (empty($postId)) {
        $response['message'] = 'ID de publication manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        // Récupérer UNIQUEMENT les commentaires de premier niveau (parent_comment_id IS NULL)
        $stmt = $pdo->prepare("
            SELECT 
                c.comment_id, 
                c.user_id, 
                u.prenom, 
                u.nom, 
                u.profile_picture_url, 
                c.comment_text, 
                c.created_at,
                (SELECT COUNT(*) FROM commentreactions cr WHERE cr.comment_id = c.comment_id) AS comment_reaction_count,
                (SELECT cr.reaction_type FROM commentreactions cr WHERE cr.comment_id = c.comment_id AND cr.user_id = ?) AS user_reaction_type
            FROM 
                comments c
            JOIN 
                users u ON c.user_id = u.user_id
            WHERE 
                c.post_id = ? AND c.parent_comment_id IS NULL
            ORDER BY 
                c.created_at ASC
        ");
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $postId, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['message'] = 'Commentaires récupérés avec succès.';
        $response['comments'] = $comments;

    } catch (PDOException $e) {
        error_log("Database error in get_comments.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
