<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À restreindre en production
require_once 'database.php'; // Assurez-vous que ce chemin est correct

$data = json_decode(file_get_contents('php://input'), true);
$inviterId = $data['inviter_id'] ?? null;
$inviteeId = $data['invitee_id'] ?? null;

if (empty($inviterId) || empty($inviteeId)) {
    echo json_encode(['status' => 'error', 'message' => 'IDs inviter/invitee manquants.']);
    exit();
}

if ($inviterId == $inviteeId) {
    echo json_encode(['status' => 'error', 'message' => 'Vous ne pouvez pas vous envoyer une demande d\'ami à vous-même.']);
    exit();
}

try {
    // Vérifier si une demande existe déjà dans un sens ou dans l'autre
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Invitations WHERE (inviter_id = :inviter_id AND invitee_id = :invitee_id) OR (inviter_id = :invitee_id AND invitee_id = :inviter_id)");
    $stmt->execute(['inviter_id' => $inviterId, 'invitee_id' => $inviteeId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Une demande d\'ami est déjà en cours ou existe déjà.']);
        exit();
    }

    // Vérifier s'ils sont déjà amis
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Amis WHERE user_id = :inviter_id AND ami_id = :invitee_id");
    $stmt->execute(['inviter_id' => $inviterId, 'invitee_id' => $inviteeId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Vous êtes déjà amis.']);
        exit();
    }

    // Insérer la nouvelle invitation
    $stmt = $pdo->prepare("INSERT INTO Invitations (inviter_id, invitee_id) VALUES (:inviter_id, :invitee_id)");
    $stmt->execute(['inviter_id' => $inviterId, 'invitee_id' => $inviteeId]);

    echo json_encode(['status' => 'success', 'message' => 'Demande d\'ami envoyée.']);

} catch (PDOException $e) {
    error_log("Erreur PDO dans send_friend_request.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}
?>