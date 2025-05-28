<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Charge automatiquement PHPMailer
require 'config.php';          // Contient les constantes SMTP et connexion

function sendValidationEmail($to, $code) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Code de validation';
        $mail->Body = "
    <div style='font-family: Georgia, serif; font-size: 16px; color: #333;'>
        <p>Bonjour,</p>
        
        <p>J'espère que ce message vous trouve en parfaite santé.</p>
        
        <p>Veuillez trouver ci-dessous votre code de validation. Merci de le conserver précieusement :</p>
        
        <p style='font-size: 18px; font-weight: bold; color: #004080;'>Code de validation : <b>$code</b></p>
        
        <p>Si vous n'êtes pas à l'origine de cette demande, je vous invite à ignorer ce message ou à nous contacter sans délai.</p>
        
        <p>Je vous remercie pour votre confiance.</p>
        
        <p>Avec toute ma considération,</p>
        <p style='margin-top: 10px;'>Cordialement,<br>
        <em>Votre dévoué serviteur</em></p>
    </div>
";


        $mail->send();
    } catch (Exception $e) {
        echo "Erreur lors de l'envoi de l'e-mail : {$mail->ErrorInfo}";
    }
}
?>
