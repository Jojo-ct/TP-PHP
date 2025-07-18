<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => '', 'posts' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = $data['user_id'] ?? null; // L'ID de l'utilisateur qui fait la requête (admin ou modérateur)

    if (empty($userId)) {
        $response['message'] = 'ID utilisateur manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        // Vérifier si l'utilisateur a le rôle d'admin ou de modérateur pour accéder à cette fonctionnalité
        $userRoleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $userRoleStmt->bindParam(1, $userId, PDO::PARAM_INT);
        $userRoleStmt->execute();
        $userRole = $userRoleStmt->fetchColumn();

        if ($userRole !== 'admin' && $userRole !== 'moderator') {
            $response['message'] = 'Accès non autorisé. Seuls les administrateurs et modérateurs peuvent voir les posts signalés.';
            echo json_encode($response);
            exit();
        }

        // Récupérer les posts qui ont été signalés, avec le nombre de signalements
        // et les informations de l'auteur du post
        $stmt = $pdo->prepare("
            SELECT 
                p.post_id, 
                p.user_id, 
                u.prenom, 
                u.nom, 
                u.profile_picture_url, 
                p.description, 
                p.image_url, 
                p.created_at,
                COUNT(pr.report_id) AS report_count
            FROM 
                posts p
            JOIN 
                users u ON p.user_id = u.user_id
            JOIN 
                post_reports pr ON p.post_id = pr.post_id
            WHERE 
                p.status = 'active' -- Ou un autre statut si vous gérez des posts déjà masqués
            GROUP BY 
                p.post_id
            ORDER BY 
                report_count DESC, p.created_at DESC
        ");
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['message'] = 'Posts signalés récupérés avec succès.';
        $response['posts'] = $posts;

    } catch (PDOException $e) {
        error_log("Database error in get_reported_posts.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
