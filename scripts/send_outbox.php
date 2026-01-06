<?php
// Outbox sender: uses PHPMailer SMTP if available; falls back to mail()
// Usage: php scripts/send_outbox.php

require_once __DIR__ . '/../includes/db_config.php';
date_default_timezone_set('UTC');

// Load mailer config
$cfg = @include __DIR__ . '/../includes/mailer_config.php';
if (!is_array($cfg)) { $cfg = []; }

// Try to load PHPMailer from vendor if present
$hasPHPMailer = false;
@include_once __DIR__ . '/../vendor/autoload.php';
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) { $hasPHPMailer = true; }

// Fetch a small batch to avoid long locks
$batch = 20;
$stmt = $pdo->prepare("SELECT id, `to`, `subject`, `body` FROM mail_outbox WHERE sent_at IS NULL ORDER BY id ASC LIMIT {$batch}");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No pending emails.\n";
    exit(0);
}

$sent = 0; $failed = 0;
foreach ($rows as $r) {
    $id = (int)$r['id'];
    $to = (string)$r['to'];
    $subject = (string)$r['subject'];
    $body = (string)$r['body'];
    $error = null;

    if ($hasPHPMailer) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'] ?? 'localhost';
            $mail->Port       = (int)($cfg['port'] ?? 25);
            $enc              = $cfg['encryption'] ?? '';
            if ($enc === 'ssl') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc === 'tls') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
            $mail->SMTPAuth   = !empty($cfg['username']);
            if ($mail->SMTPAuth) {
                $mail->Username = $cfg['username'];
                $mail->Password = $cfg['password'] ?? '';
            }
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($cfg['from_email'] ?? 'no-reply@localhost', $cfg['from_name'] ?? 'AK23 Downloads');
            if (!empty($cfg['reply_to'])) { $mail->addReplyTo($cfg['reply_to']); }
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $body;
            $mail->send();
            $ok = true;
        } catch (Throwable $e) {
            $ok = false; $error = 'PHPMailer: ' . $e->getMessage();
        }
    } else {
        // Fallback: mail()
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/plain; charset=utf-8';
        $headers[] = 'From: ' . ($cfg['from_name'] ?? 'AK23 Downloads') . ' <' . ($cfg['from_email'] ?? 'no-reply@localhost') . '>';
        $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$ok) { $error = 'mail() failed'; }
    }

    if ($ok) {
        $pdo->prepare('UPDATE mail_outbox SET sent_at = NOW(), last_error = NULL WHERE id = ?')->execute([$id]);
        $sent++;
    } else {
        $pdo->prepare('UPDATE mail_outbox SET last_error = ? WHERE id = ?')->execute([$error, $id]);
        $failed++;
    }
}

echo "Sent: {$sent}, Failed: {$failed}\n";
