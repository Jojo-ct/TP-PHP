<?php
header('Content-Type: application/json');
require_once 'database.php'; // Inclut votre fichier de connexion à la base de données

$data = json_decode(file_get_contents('php://input'), true);

$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID utilisateur manquant.']);
    exit;
}

try {
    // Utilisez la variable $pdo directement car elle est définie dans database.php
    // $pdo = get_db_connection(); // Cette ligne est supprimée

    // Sélectionne toutes les conversations où l'utilisateur actuel est user1 ou user2
    // Joint les informations des deux utilisateurs participants et du dernier message
    $stmt = $pdo->prepare("
        SELECT
            c.conversation_id,
            c.last_message_id,
            c.created_at,
            c.updated_at,
            u1.user_id AS user1_id,
            u1.prenom AS user1_first_name,
            u1.nom AS user1_last_name,
            u1.profile_picture_url AS user1_profile_picture_url,
            u2.user_id AS user2_id,
            u2.prenom AS user2_first_name,
            u2.nom AS user2_last_name,
            u2.profile_picture_url AS user2_profile_picture_url,
            m.message_text AS last_message_text,
            m.created_at AS last_message_time
        FROM
            conversations c
        JOIN
            users u1 ON c.user1_id = u1.user_id
        JOIN
            users u2 ON c.user2_id = u2.user_id
        LEFT JOIN
            messages m ON c.last_message_id = m.message_id
        WHERE
            c.user1_id = ? OR c.user2_id = ?
        ORDER BY
            c.updated_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'conversations' => $conversations]);

} catch (PDOException $e) {
    error_log("Erreur de base de données lors de la récupération des conversations: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erreur générale lors de la récupération des conversations: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue : ' . $e->getMessage()]);
}
?>
