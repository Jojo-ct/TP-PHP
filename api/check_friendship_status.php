<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // À restreindre en production
require_once 'database.php'; // Assurez-vous que ce chemin est correct

$data = json_decode(file_get_contents('php://input'), true);
$user1Id = $data['user1_id'] ?? null; // L'utilisateur courant
$user2Id = $data['user2_id'] ?? null; // L'utilisateur dont on consulte le profil

if (empty($user1Id) || empty($user2Id)) {
    echo json_encode(['status' => 'error', 'message' => 'IDs utilisateur manquants.']);
    exit();
}

try {
    // Cas 1: Sont-ils déjà amis ?
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Amis WHERE user_id = :user1_id AND ami_id = :user2_id");
    $stmt->execute(['user1_id' => $user1Id, 'user2_id' => $user2Id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['status' => 'success', 'friendship' => 'friends']);
        exit();
    }

    // Cas 2: user1 a envoyé une demande à user2 ? (Demande envoyée)
    $stmt = $pdo->prepare("SELECT invitation_id FROM Invitations WHERE inviter_id = :user1_id AND invitee_id = :user2_id");
    $stmt->execute(['user1_id' => $user1Id, 'user2_id' => $user2Id]);
    if ($invitation = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['status' => 'success', 'friendship' => 'pending_sent', 'invitation_id' => $invitation['invitation_id']]);
        exit();
    }

    // Cas 3: user2 a envoyé une demande à user1 ? (Demande reçue)
    $stmt = $pdo->prepare("SELECT invitation_id FROM Invitations WHERE inviter_id = :user2_id AND invitee_id = :user1_id");
    $stmt->execute(['user2_id' => $user2Id, 'user1_id' => $user1Id]);
    if ($invitation = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['status' => 'success', 'friendship' => 'pending_received', 'invitation_id' => $invitation['invitation_id']]);
        exit();
    }

    // Cas 4: Pas amis et aucune demande en cours
    echo json_encode(['status' => 'success', 'friendship' => 'not_friends']);

} catch (PDOException $e) {
    error_log("Erreur PDO dans check_friendship_status.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}
?>