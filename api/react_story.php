 <?php
        header('Content-Type: application/json');
        include 'database.php'; // Assurez-vous que le chemin est correct

        $response = ['success' => false, 'message' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            $storyId = $data['story_id'] ?? null;
            $userId = $data['user_id'] ?? null;
            $reaction = $data['reaction'] ?? null;

            if (empty($storyId) || empty($userId) || empty($reaction)) {
                $response['message'] = 'Données manquantes pour la réaction à la story.';
                echo json_encode($response);
                exit();
            }

            try {
                // Vérifier si l'utilisateur a déjà réagi à cette story
                $stmt = $pdo->prepare("SELECT reaction_id FROM story_reactions WHERE story_id = :story_id AND user_id = :user_id");
                $stmt->bindParam(':story_id', $storyId, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $existingReaction = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingReaction) {
                    // Mettre à jour la réaction existante
                    $stmt = $pdo->prepare("UPDATE story_reactions SET reaction_type = :reaction_type, created_at = NOW() WHERE reaction_id = :reaction_id");
                    $stmt->bindParam(':reaction_type', $reaction, PDO::PARAM_STR);
                    $stmt->bindParam(':reaction_id', $existingReaction['reaction_id'], PDO::PARAM_INT);
                } else {
                    // Insérer une nouvelle réaction
                    $stmt = $pdo->prepare("INSERT INTO story_reactions (story_id, user_id, reaction_type) VALUES (:story_id, :user_id, :reaction_type)");
                    $stmt->bindParam(':story_id', $storyId, PDO::PARAM_INT);
                    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->bindParam(':reaction_type', $reaction, PDO::PARAM_STR);
                }

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Réaction à la story enregistrée avec succès.';
                } else {
                    $response['message'] = 'Erreur lors de l\'enregistrement de la réaction.';
                }

            } catch (PDOException $e) {
                error_log("Database error in react_story.php: " . $e->getMessage());
                $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
            }
        } else {
            $response['message'] = 'Méthode de requête non autorisée.';
        }

        echo json_encode($response);
        ?>
        