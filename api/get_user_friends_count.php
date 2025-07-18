<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À restreindre en production
require_once 'database.php'; // Assurez-vous que ce chemin est correct

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? null;

if (empty($userId)) {
    echo json_encode(['status' => 'error', 'message' => 'L\'ID utilisateur est manquant.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM Amis WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $count = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'count' => $count]);

} catch (PDOException $e) {
    error_log("Erreur PDO dans get_user_friends_count.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}
?>