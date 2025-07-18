<?php

header('Content-Type: application/json'); // Indique que la réponse est du JSON

try {
    // Assurez-vous que le chemin vers database.php est correct
    include 'database.php'; // Utilisez ../database.php si database.php est dans le dossier parent (reseau/)
    // include 'database.php'; // Ou utilisez 'database.php' si database.php est dans le même dossier (api/)

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Erreur de connexion à la base de données: " . $e->getMessage()]);
    exit();
}

// Requête SQL pour joindre les tables Posts, Users, PostReactions et Comments
// Nous utilisons LEFT JOIN pour inclure les posts même s'ils n'ont pas de réactions ou de commentaires
$sql = "
    SELECT
        p.post_id,
        p.description,
        p.image_url,
        p.created_at,
        u.user_id,
        u.nom,
        u.prenom,
        u.profile_picture_url,
        COUNT(DISTINCT pr.reaction_id) AS reaction_count,  -- Compte le nombre de réactions uniques
        COUNT(DISTINCT c.comment_id) AS comment_count      -- Compte le nombre de commentaires uniques
    FROM
        posts p
    INNER JOIN
        users u ON p.user_id = u.user_id
    LEFT JOIN
        postreactions pr ON p.post_id = pr.post_id
    LEFT JOIN
        comments c ON p.post_id = c.post_id
    GROUP BY    -- Important: regrouper par post pour que COUNT fonctionne
        p.post_id, u.user_id, u.nom, u.prenom, u.profile_picture_url, p.description, p.image_url, p.created_at
    ORDER BY
        p.created_at DESC
";

$posts = [];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'posts' => $posts]);

} catch (PDOException $e) {
    error_log("Database error in get_posts.php: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Erreur lors de la récupération des posts: " . $e->getMessage()]);
    exit();
}

?>