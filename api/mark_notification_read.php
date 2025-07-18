<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $notificationId = $data['notification_id'] ?? null;
    $userId = $data['user_id'] ?? null; // Pour s'assurer que seul le destinataire peut marquer comme lu

    if (empty($notificationId) || empty($userId)) {
        $response['message'] = 'ID de notification ou ID utilisateur manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        // Marquer la notification comme lue si elle appartient à l'utilisateur spécifié
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_id = ?");
        $stmt->bindParam(1, $notificationId, PDO::PARAM_INT);
        $stmt->bindParam(2, $userId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Notification marquée comme lue.';
            } else {
                $response['message'] = 'Notification introuvable ou vous n\'êtes pas autorisé à la modifier.';
            }
        } else {
            $response['message'] = 'Erreur lors de la mise à jour de la notification.';
        }

    } catch (PDOException $e) {
        error_log("Database error in mark_notification_read.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
