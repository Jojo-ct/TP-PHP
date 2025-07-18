<?php
// Ce script n'est PAS destiné à être appelé directement via une requête HTTP.
// C'est une fonction utilitaire à inclure dans d'autres scripts PHP.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Assurez-vous que PHPMailer est inclus
// Vous devrez peut-être ajuster ce chemin en fonction de votre installation de PHPMailer
// Si vous utilisez Composer, ce n'est pas nécessaire, juste 'vendor/autoload.php'
require '../vendor/autoload.php'; // Chemin vers l'autoload de Composer si utilisé

function sendEmail($toEmail, $toName, $subject, $bodyHtml, $altBody = '') {
    $mail = new PHPMailer(true); // Passer 'true' active les exceptions

    try {
        // Configuration du serveur SMTP (intégrée ici)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Votre hôte SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mariejosezon@gmail.com'; // Votre adresse email complète
        $mail->Password   = 'hdox duzo cwja geps';    // Votre mot de passe d'application ou de compte
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ENCRYPTION_STARTTLS ou ENCRYPTION_SMTPS
        $mail->Port       = 465;                      // 587 pour STARTTLS, 465 pour SMTPS

        $mail->setFrom('mariejosezon@gmail.com', 'Mon Reseau Social'); // Expéditeur
        $mail->isHTML(true); // Active le format HTML pour le corps de l'email
        $mail->CharSet    = 'UTF-8'; // Encodage des caractères

        // Destinataires
        $mail->addAddress($toEmail, $toName);

        // Contenu
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $altBody; // Version texte brut pour les clients email qui ne supportent pas le HTML

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("L'envoi de l'email a échoué. Erreur PHPMailer: {$mail->ErrorInfo}");
        return false;
    }
}
?>

