<?php
header('Content-Type: application/json');
require_once 'database.php'; // Assurez-vous que le chemin est correct

$response = ['success' => false, 'message' => '', 'users' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = $data['user_id'] ?? null; // L'ID de l'utilisateur qui fait la requête (admin ou modérateur)

    if (empty($userId)) {
        $response['message'] = 'ID utilisateur manquant.';
        echo json_encode($response);
        exit();
    }

    try {
        // Vérifier si l'utilisateur a le rôle d'admin ou de modérateur
        $userRoleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $userRoleStmt->bindParam(1, $userId, PDO::PARAM_INT);
        $userRoleStmt->execute();
        $userRole = $userRoleStmt->fetchColumn();

        if ($userRole !== 'admin' && $userRole !== 'moderator') {
            $response['message'] = 'Accès non autorisé. Seuls les administrateurs et modérateurs peuvent voir les utilisateurs à modérer.';
            echo json_encode($response);
            exit();
        }

        // Récupérer les utilisateurs qui ont été signalés, ou qui ont un statut spécial
        // Ici, nous récupérons tous les utilisateurs et comptons leurs signalements pour les afficher
        // Vous pouvez ajuster cette logique pour ne récupérer que les utilisateurs "problématiques"
        $stmt = $pdo->prepare("
            SELECT 
                u.user_id, 
                u.prenom, 
                u.nom, 
                u.email, 
                u.profile_picture_url, 
                u.status, -- Assurez-vous que votre table users a une colonne 'status' (ex: 'active', 'blocked')
                COUNT(pr.report_id) AS report_count
            FROM 
                users u
            LEFT JOIN 
                posts p ON u.user_id = p.user_id
            LEFT JOIN 
                post_reports pr ON p.post_id = pr.post_id
            WHERE 
                u.role = 'client' -- Ne pas afficher les admins/modérateurs dans cette liste
            GROUP BY 
                u.user_id
            ORDER BY 
                report_count DESC, u.created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['message'] = 'Utilisateurs récupérés avec succès.';
        $response['users'] = $users;

    } catch (PDOException $e) {
        error_log("Database error in get_users_for_moderation.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Méthode de requête non autorisée.';
}

echo json_encode($response);
?>
