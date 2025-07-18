<?php
header('Content-Type: application/json');
include 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => '', 'stories' => []];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Sélectionne les stories qui ne sont pas expirées
        // Jointure avec la table users pour obtenir les infos de l'utilisateur
        // GROUP BY user_id pour n'afficher qu'une "bulle" par utilisateur dans la sidebar,
        // mais on récupérera toutes les stories d'un utilisateur quand on cliquera sur sa bulle.
        // Pour la liste principale, on veut la dernière story de chaque utilisateur qui en a une active.
        $sql = "
            SELECT
                s.user_id,
                u.prenom AS first_name,
                u.nom AS last_name,
                u.profile_picture_url,
                MAX(s.created_at) as latest_story_created_at,
                (SELECT media_url FROM stories WHERE user_id = s.user_id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1) as latest_media_url,
                (SELECT story_id FROM stories WHERE user_id = s.user_id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1) as latest_story_id
            FROM
                stories s
            JOIN
                users u ON s.user_id = u.user_id
            WHERE
                s.expires_at > NOW()
            GROUP BY
                s.user_id, u.prenom, u.nom, u.profile_picture_url
            ORDER BY
                latest_story_created_at DESC;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['stories'] = $stories;

    } catch (PDOException $e) {
        error_log("Database error in get_stories.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ce bloc POST est pour récupérer TOUTES les stories d'un utilisateur spécifique pour la visionneuse
    $data = json_decode(file_get_contents('php://input'), true);
    $targetUserId = $data['user_id'] ?? null;

    if (empty($targetUserId)) {
        $response['message'] = 'ID utilisateur cible manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        $sql = "
            SELECT
                s.story_id,
                s.user_id,
                s.media_url,
                s.created_at,
                s.expires_at,
                u.prenom AS first_name,
                u.nom AS last_name,
                u.profile_picture_url
            FROM
                stories s
            JOIN
                users u ON s.user_id = u.user_id
            WHERE
                s.user_id = :user_id AND s.expires_at > NOW()
            ORDER BY
                s.created_at ASC;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
        $stmt->execute();
        
        $userStories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['stories'] = $userStories;

    } catch (PDOException $e) {
        error_log("Database error in get_stories.php (POST): " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
