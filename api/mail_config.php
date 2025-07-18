<?php
// Inclure le fichier autoloader de Composer si vous l'utilisez
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Fonction pour obtenir une instance de PHPMailer préconfigurée
function getMailer() {
    $mail = new PHPMailer(true); // Passer true pour activer les exceptions

    // Configuration SMTP (Gmail, par exemple)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Ou votre hôte SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mariejosezon@gmail.com'; // Votre adresse email complète
    $mail->Password   = 'hdox duzo cwja geps';    // Votre mot de passe d'application ou de compte
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ENCRYPTION_STARTTLS ou ENCRYPTION_SMTPS
    $mail->Port       = 465;                        // 587 pour STARTTLS, 465 pour SMTPS

    $mail->setFrom('mariejosezon@gmail.com', 'Mon Reseau Social'); // Expéditeur
    $mail->isHTML(true); // Active le format HTML pour le corps de l'email
    $mail->CharSet = 'UTF-8'; // Encodage des caractères

    return $mail;
}
?>