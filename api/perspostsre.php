<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À restreindre en production

require_once 'database.php'; // Inclut la connexion PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = $data['user_id'] ?? null;
    $postId = $data['post_id'] ?? null;

    if (empty($userId) || empty($postId)) {
        echo json_encode(["success" => false, "message" => "Les IDs de l'utilisateur et du post sont requis."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT reaction_type FROM postreactions WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$userId, $postId]);
        $existingReaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingReaction) {
            echo json_encode([
                "success" => true,
                "reaction" => $existingReaction['reaction_type']
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "reaction" => "none" // Aucune réaction trouvée
            ]);
        }

    } catch (\PDOException $e) {
        error_log("Erreur PDO dans perspostsre.php: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Erreur de base de données lors de la récupération de la réaction personnelle."]);
    }

} else {
    echo json_encode(["success" => false, "message" => "Méthode de requête non autorisée."]);
}
?>