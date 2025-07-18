<?php
try{
    $pdo = new
PDO('mysql:host=localhost;dbname=reseau;charset=utf8',
'root', '');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

}

catch (PDOException $e) {
       echo json_encode(["error" => "Erreur de connexion à la base de données: " . $e->getMessage()]);
       exit();

}
?>