<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => '', 'notifications' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = $data['user_id'] ?? null; // L'ID de l'utilisateur pour lequel on veut récupérer les notifications

    if (empty($userId)) {
        $response['message'] = 'ID utilisateur manquant pour r\u00e9cup\u00e9rer les notifications.';
        echo json_encode($response);
        exit();
    }

    try {
        // R\u00e9cup\u00e9rer les notifications pour l'utilisateur sp\u00e9cifi\u00e9
        // Joindre avec la table 'users' pour obtenir les informations de l'exp\u00e9diteur si 'sender_id' est pr\u00e9sent
        $stmt = $pdo->prepare("
            SELECT 
                n.notification_id, 
                n.recipient_id, 
                n.sender_id, 
                n.type, 
                n.content, 
                n.is_read, 
                n.created_at,
                s.prenom AS sender_first_name,
                s.nom AS sender_last_name,
                s.profile_picture_url AS sender_profile_picture_url
            FROM 
                notifications n
            LEFT JOIN 
                users s ON n.sender_id = s.user_id
            WHERE 
                n.recipient_id = ?
            ORDER BY 
                n.created_at DESC
        ");
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['message'] = 'Notifications r\u00e9cup\u00e9r\u00e9es avec succ\u00e8s.';
        $response['notifications'] = $notifications;

    } catch (PDOException $e) {
        error_log("Database error in get_notifications.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de donn\u00e9es : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'M\u00e9thode de requ\u00eate non autoris\u00e9e.';
}

echo json_encode($response);
?>
