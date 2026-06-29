<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendGenericEmail(string $to, string $subject, string $body_text): void {
    sendValidationEmail($to, 0, $subject, $body_text);
}

function sendValidationEmail(string $to, string|int $code, string $subject = '', string $body_text = ''): void {
    // Fallback dev : écriture dans un fichier si PHPMailer absent
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        $log = date('Y-m-d H:i:s') . " | Email: $to | Code: $code\n";
        file_put_contents(__DIR__ . '/mail_dev.log', $log, FILE_APPEND);
        return;
    }

    require_once __DIR__ . '/vendor/autoload.php';
    // Les constantes SMTP_* sont déjà définies par config.php (getenv en prod)

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
        $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $mail->CharSet    = 'UTF-8';

        $from      = defined('SMTP_USER') ? SMTP_USER : 'noreply@forgab.com';
        $from_name = 'Forgab';
        $mail->setFrom($from, $from_name);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject ?: 'Code de validation – Forgab';
        $mail->Body    = $body_text ?: "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;'>
              <h2 style='color:#0d6efd;'>Forgab</h2>
              <p>Voici votre code de validation :</p>
              <div style='font-size:2em;font-weight:bold;color:#0d6efd;letter-spacing:8px;text-align:center;padding:16px;background:#f1f3f4;border-radius:8px;'>$code</div>
              <p style='margin-top:16px;color:#888;font-size:.9em;'>Ce code expire dans 24h.</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        // Log l'erreur sans planter l'inscription
        error_log("PHPMailer erreur : {$mail->ErrorInfo}");
        // Fallback : log local
        $log = date('Y-m-d H:i:s') . " | Email: $to | Code: $code | Erreur: {$mail->ErrorInfo}\n";
        file_put_contents(__DIR__ . '/mail_dev.log', $log, FILE_APPEND);
    }
}
