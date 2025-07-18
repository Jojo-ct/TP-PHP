<?php
header('Content-Type: application/json');
require_once 'database.php'; // Inclut votre fichier de connexion à la base de données

$data = json_decode(file_get_contents('php://input'), true);

$user_id1 = $data['user_id1'] ?? null;
$user_id2 = $data['user_id2'] ?? null;

if (!$user_id1 || !$user_id2) {
    echo json_encode(['status' => 'error', 'message' => 'IDs utilisateur manquants.']);
    exit;
}

try {
    // Utilisez la variable $pdo directement car elle est définie dans database.php
    // $pdo = get_db_connection(); // Cette ligne est supprimée

    // 1. Trouver l'ID de la conversation entre les deux utilisateurs
    $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->execute([$user_id1, $user_id2, $user_id2, $user_id1]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        // Aucune conversation trouvée, retourner un tableau de messages vide
        echo json_encode(['status' => 'success', 'messages' => []]);
        exit;
    }

    $conversation_id = $conversation['conversation_id'];

    // 2. Récupérer tous les messages de cette conversation, ordonnés par date
    $stmt = $pdo->prepare("SELECT message_id, sender_id, message_text, image_url, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'messages' => $messages]);

} catch (PDOException $e) {
    error_log("Erreur de base de données lors de la récupération des messages: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erreur générale lors de la récupération des messages: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue : ' . $e->getMessage()]);
}
?>
