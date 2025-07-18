<?php
header('Content-Type: application/json');
require_once 'database.php';

$input = json_decode(file_get_contents('php://input'), true);

$postId = $input['post_id'] ?? null;
$reaction = $input['reaction'] ?? null; // 'like', 'love', 'haha', 'wouah', 'triste', 'colere', ou vide pour retirer
$userId = $input['user_id'] ?? null;

if (!$postId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Post ID ou User ID manquant.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Vérifier la réaction existante de l'utilisateur sur ce post
    $stmt = $pdo->prepare("SELECT reaction_type FROM postreactions WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $existingReaction = $stmt->fetchColumn();

    $message = '';
    if ($reaction === '') { // L'utilisateur veut retirer sa réaction
        if ($existingReaction) {
            $stmt = $pdo->prepare("DELETE FROM postreactions WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $message = 'Réaction retirée.';
        } else {
            $message = 'Aucune réaction à retirer.';
        }
    } else { // L'utilisateur veut ajouter ou changer sa réaction
        if ($existingReaction) {
            if ($existingReaction === $reaction) {
                // Même réaction, ne rien faire ou retirer (selon UX, ici on ne fait rien)
                $message = 'Réaction déjà enregistrée.';
            } else {
                // Changer la réaction
                $stmt = $pdo->prepare("UPDATE postreactions SET reaction_type = ? WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$reaction, $postId, $userId]);
                $message = 'Réaction mise à jour.';
            }
        } else {
            // Ajouter une nouvelle réaction
            $stmt = $pdo->prepare("INSERT INTO postreactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)");
            $stmt->execute([$postId, $userId, $reaction]);
            $message = 'Réaction ajoutée.';
        }
    }

    // 2. Récupérer le nouveau compte de réactions pour ce post
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM postreactions WHERE post_id = ?");
    $stmt->execute([$postId]);
    $newReactionCount = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_reaction_count' => $newReactionCount, // Retourne le nouveau compte
        'user_reaction_type' => $reaction // Retourne le type de réaction actuel de l'utilisateur
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>
