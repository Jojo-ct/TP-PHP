<?php
header('Content-Type: application/json'); // La réponse sera du JSON
require_once 'database.php'; // Inclut la connexion PDO
  $data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? null; // Utilisez l'opérateur de coalescence null pour éviter les index indéfinis si la clé est manquante

if (!$userId) {
  echo json_encode(['status' => 'error', 'message' => 'ID utilisateur manquant']);
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
  echo json_encode(['status' => 'success', 'user' => $user]);
} else {
  echo json_encode(['status' => 'error', 'message' => 'Utilisateur non trouvé']);
}
?>