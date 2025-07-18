<?php
header('Content-Type: application/json'); // La réponse sera du JSON
require_once 'database.php'; // Inclut la connexion PDO

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['user_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'L\'ID utilisateur est manquant.'
        ]);
        exit();
    }

    $userId = $data['user_id'];

    // Obtenir le nombre d'amis
    $reqCount = $pdo->prepare("SELECT COUNT(*) AS count FROM users JOIN Amis ON users.user_id = Amis.ami_id WHERE Amis.user_id = :user_id");
    $reqCount->execute(['user_id' => $userId]);
    $count = $reqCount->fetchColumn(); // fetchColumn renvoie directement la valeur

    // Obtenir les détails des amis
    $reqDetails = $pdo->prepare("SELECT users.nom, users.user_id, users.prenom, users.profile_picture_url FROM users JOIN Amis ON users.user_id = Amis.ami_id WHERE Amis.user_id = :user_id");
    $reqDetails->execute(['user_id' => $userId]);
    $userDetails = $reqDetails->fetchAll(PDO::FETCH_ASSOC);

    if ($count == 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Aucun ami trouvé.',
            'count' => $count
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'friends' => $userDetails,
            'count' => $count
        ]);
    }

} catch (PDOException $e) {
    // Journalise l'erreur pour le débogage
    error_log("Erreur de base de données : " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Une erreur de base de données est survenue. Veuillez réessayer plus tard.'
    ]);
} catch (Exception $e) {
    // Capture toute autre erreur inattendue
    error_log("Erreur générale : " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Une erreur inattendue est survenue.'
    ]);
}
?>
