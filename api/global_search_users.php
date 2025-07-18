<?php
header('Content-Type: application/json');
include 'database.php'; // Adaptez le chemin

$response = ['status' => 'error', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $searchQuery = $_GET['q'] ?? '';

    if (empty($searchQuery)) {
        $response['status'] = 'success'; // Pas une erreur, juste pas de recherche
        $response['users'] = [];
        echo json_encode($response);
        exit();
    }

    try {
        $sql = "
            SELECT
                user_id,
                prenom AS first_name,
                nom AS last_name,
                profile_picture_url
            FROM
                users
            WHERE
                prenom LIKE :search_query OR nom LIKE :search_query
            LIMIT 10
        ";

        $stmt = $pdo->prepare($sql);
        $searchTerm = '%' . $searchQuery . '%';
        $stmt->bindParam(':search_query', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = 'success';
        $response['users'] = $users;

    } catch (PDOException $e) {
        error_log("Database error in global_search_users.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>