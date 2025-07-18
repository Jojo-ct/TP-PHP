<?php
header('Content-Type: application/json');
// Assurez-vous que le chemin vers database.php est correct
include 'database.php'; 

$response = ['status' => 'error', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Recherche par GET pour les requêtes simples
    $searchQuery = $_GET['q'] ?? '';
    $currentUserId = $_GET['user_id'] ?? null; // L'ID de l'utilisateur qui effectue la recherche

    if (empty($searchQuery)) {
        $response['message'] = 'Veuillez fournir un terme de recherche.';
        echo json_encode($response);
        exit();
    }

    if (empty($currentUserId)) {
        $response['message'] = 'ID utilisateur actuel manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        // Rechercher des utilisateurs par prénom ou nom,
        // exclure l'utilisateur actuel et les amis existants/en attente
        $sql = "
            SELECT
                u.user_id,
                u.prenom AS first_name,
                u.nom AS last_name,
                u.profile_picture_url
            FROM
                users u
            WHERE
                (u.prenom LIKE :search_query OR u.nom LIKE :search_query)
                AND u.user_id != :current_user_id
                AND u.user_id NOT IN (
                    SELECT
                        CASE
                            WHEN user_id1 = :current_user_id THEN user_id2
                            ELSE user_id1
                        END
                    FROM
                        friendships
                    WHERE
                        (user_id1 = :current_user_id OR user_id2 = :current_user_id)
                )
            LIMIT 10
        ";

        $stmt = $pdo->prepare($sql);
        $searchTerm = '%' . $searchQuery . '%';
        $stmt->bindParam(':search_query', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($users) {
            $response['status'] = 'success';
            $response['users'] = $users;
        } else {
            $response['status'] = 'success'; // Pas une erreur, juste aucun résultat
            $response['message'] = 'Aucun utilisateur trouvé.';
            $response['users'] = [];
        }

    } catch (PDOException $e) {
        error_log("Database error in search_users.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>