<?php
header('Content-Type: application/json'); // Indique que la réponse est du JSON

try {
    // Assurez-vous que le chemin vers database.php est correct
    // Exemple si database.php est dans reseau/config/
    include 'database.php';
    // Ou si database.php est dans reseau/
    // include '../database.php';
    // Ou si database.php est dans le même dossier api/ (moins commun)
    // include 'database.php';

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Erreur de connexion à la base de données: " . $e->getMessage()]);
    exit();
}

// Récupérer l'ID utilisateur à partir du corps de la requête POST
$data = json_decode(file_get_contents('php://input'), true);
$targetUserId = $data['user_id'] ?? null;

if (!$targetUserId) {
    echo json_encode(['status' => 'error', 'message' => 'ID utilisateur manquant pour récupérer les posts.']);
    exit;
}

// Requête SQL pour joindre les tables Posts et Users, filtrer par user_id
$sql = "
    SELECT
        p.post_id,
        p.description,
        p.image_url,
        p.created_at,
        u.user_id,
        u.nom as last_name,
        u.prenom as first_name,
        u.profile_picture_url
    FROM
        posts p
    INNER JOIN
        users u ON p.user_id = u.user_id
    WHERE
        p.user_id = :user_id  -- Filtrer les posts par l'ID de l'utilisateur
    ORDER BY
        p.created_at DESC
";

$posts = [];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT); // Lier le paramètre
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'posts' => $posts]);

} catch (PDOException $e) {
    error_log("Database error in get_user_posts.php: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Erreur lors de la récupération des posts de l'utilisateur: " . $e->getMessage()]);
    exit();
}
?>