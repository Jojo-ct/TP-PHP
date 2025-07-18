<?php
header('Content-Type: application/json'); // La réponse sera du JSON
require_once 'database.php'; // Inclut la connexion PDO
  $data = json_decode(file_get_contents('php://input'), true);

    $invitation_id = $data['invitation_id'];
    $status=$data['status'];
    if($status=='accepted'){
        $req=$pdo->prepare("SELECT invitee_id,inviter_id FROM invitations WHERE invitation_id=:id_invitation");
        $req->execute([":id_invitation"=>$invitation_id]);
        $données=$req->fetch(PDO::FETCH_ASSOC);
        $req=$pdo->prepare("INSERT INTO amis(amis.user_id,ami_id) VALUES(:user_id,:ami_id)");
        $req->execute([
            ":user_id" => $données['inviter_id'],
            ":ami_id" => $données['invitee_id']
        ]);
         $req=$pdo->prepare("INSERT INTO amis(amis.user_id,ami_id) VALUES(:user_id,:ami_id)");
        $req->execute([
            ":user_id" => $données['invitee_id'],
            ":ami_id" => $données['inviter_id']
        ]);
         $req=$pdo->prepare("DELETE FROM invitations WHERE invitation_id = :id_invitation");
    $req->execute(['id_invitation' => $invitation_id]);
    }
   if($status=='rejected'){
       $req=$pdo->prepare("DELETE FROM invitations WHERE invitation_id = :id_invitation");
       $req->execute(['id_invitation' => $invitation_id]);
   }
   echo json_encode(["status"=>"success"]);
?>