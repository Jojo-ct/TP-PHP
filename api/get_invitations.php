<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Ajoutez ceci pour CORS si nécessaire
require_once 'database.php'; // Assurez-vous que ce chemin est correct

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? null; // Utilisez l'opérateur de coalescence null pour éviter les index indéfinis si la clé est manquante

// Validation de base
if (empty($userId)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'L\'ID utilisateur est manquant.'
    ]);
    exit();
}

try {
    // 1. Obtenir le nombre d'invitations pour l'utilisateur
    // Cette requête vérifie correctement la table Invitations pour invitee_id
    $reqCount = $pdo->prepare("
        SELECT COUNT(*) 
        FROM Invitations 
        WHERE invitee_id = :user_id
    ");
    $reqCount->execute(['user_id' => $userId]);
    $count = $reqCount->fetchColumn();

    $invitations = []; // Initialiser un tableau vide pour les invitations

    // 2. Ne récupérer les détails de l'invitation que s'il y a des invitations
    if ($count > 0) {
        $reqDetails = $pdo->prepare("
            SELECT i.invitation_id AS id, u.user_id, u.prenom , u.nom, u.profile_picture_url 
            FROM Invitations i
            JOIN Users u ON u.user_id = i.inviter_id 
            WHERE i.invitee_id = :user_id
            ORDER BY i.invitation_id DESC -- Ou par date de création si vous en aviez une
        ");
        $reqDetails->execute(['user_id' => $userId]);
        $invitations = $reqDetails->fetchAll(PDO::FETCH_ASSOC);
    }

    // Toujours retourner succès si la requête s'est exécutée sans erreurs,
    // et laisser le frontend gérer si le compte est à 0 ou non.
    echo json_encode([
        'status' => 'success',
        'invitations' => $invitations,
        'count' => $count
    ]);

} catch (PDOException $e) {
    error_log("Erreur de base de données dans get_invitations.php: " . $e->getMessage()); // Journaliser l'erreur pour le débogage
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur de base de données : ' . $e->getMessage() // Erreur plus descriptive pour le développement, mais générique pour la production
    ]);
}
?>