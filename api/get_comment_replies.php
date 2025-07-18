<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => '', 'replies' => [], 'has_more' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $parentCommentId = $data['parent_comment_id'] ?? null;
    $offset = $data['offset'] ?? 0;
    $limit = 10; // Nombre de réponses à charger par défaut

    if (empty($parentCommentId)) {
        $response['message'] = 'ID du commentaire parent manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        // Récupérer les réponses pour le commentaire parent spécifié
        // Joindre avec la table 'users' pour obtenir les informations de l'auteur de la réponse (u)
        // Et joindre avec la table 'comments' (aliassée en 'pc' pour 'parent comment') pour obtenir l'auteur du commentaire parent direct (pc_u)
        $stmt = $pdo->prepare("
            SELECT 
                c.comment_id, 
                c.user_id, 
                u.prenom, 
                u.nom, 
                u.profile_picture_url, 
                c.comment_text, 
                c.created_at,
                c.parent_comment_id,
                pc_u.prenom AS parent_comment_first_name,
                pc_u.nom AS parent_comment_last_name
            FROM 
                comments c
            JOIN 
                users u ON c.user_id = u.user_id
            LEFT JOIN 
                comments pc ON c.parent_comment_id = pc.comment_id -- Jointure pour le commentaire parent direct
            LEFT JOIN
                users pc_u ON pc.user_id = pc_u.user_id -- Jointure pour l'utilisateur du commentaire parent direct
            WHERE 
                c.parent_comment_id = ?
            ORDER BY 
                c.created_at ASC
            LIMIT ?, ?
        ");
        $stmt->bindParam(1, $parentCommentId, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
        $stmt->bindParam(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vérifier s'il y a plus de réponses après la limite actuelle
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE parent_comment_id = ?");
        $countStmt->bindParam(1, $parentCommentId, PDO::PARAM_INT);
        $countStmt->execute();
        $totalReplies = $countStmt->fetchColumn();

        $response['success'] = true;
        $response['message'] = 'Réponses récupérées avec succès.';
        $response['replies'] = $replies;
        $response['has_more'] = ($offset + $limit < $totalReplies);

    } catch (PDOException $e) {
        error_log("Database error in get_comment_replies.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
