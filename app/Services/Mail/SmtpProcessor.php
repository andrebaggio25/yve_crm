<?php

namespace App\Services\Mail;

use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Procesa fila email_outbox (uso desde scheduled_worker o CLI).
 */
class SmtpProcessor
{
    public static function runBatch(int $limit = 25): array
    {
        if (!MailService::isConfigured()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $rows = [];
        try {
            $rows = Database::fetchAll(
                "SELECT * FROM email_outbox WHERE status = 'pending' AND attempts < 5 ORDER BY id ASC LIMIT {$limit}"
            );
        } catch (\Throwable $e) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }
        if (!$rows) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $sent = 0;
        $failed = 0;
        $mailer = self::buildMailer();

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            Database::getInstance()->prepare(
                "UPDATE email_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = :id"
            )->execute([':id' => $id]);

            try {
                $mailer->clearAllRecipients();
                $mailer->addAddress((string) $row['to_email'], (string) ($row['to_name'] ?? ''));
                $mailer->Subject = (string) $row['subject'];
                $mailer->isHTML(true);
                $mailer->Body = (string) ($row['body_html'] ?? '');
                $mailer->AltBody = (string) ($row['body_text'] ?? strip_tags((string) ($row['body_html'] ?? '')));
                $mailer->send();

                Database::getInstance()->prepare(
                    "UPDATE email_outbox SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = :id"
                )->execute([':id' => $id]);
                $sent++;
            } catch (MailException $e) {
                $err = mb_substr($e->getMessage(), 0, 2000);
                Database::getInstance()->prepare(
                    "UPDATE email_outbox SET status = 'failed', last_error = :e WHERE id = :id"
                )->execute([':e' => $err, ':id' => $id]);
                $failed++;
            } catch (\Throwable $e) {
                $err = mb_substr($e->getMessage(), 0, 2000);
                Database::getInstance()->prepare(
                    "UPDATE email_outbox SET status = 'failed', last_error = :e WHERE id = :id"
                )->execute([':e' => $err, ':id' => $id]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => 0];
    }

    private static function buildMailer(): PHPMailer
    {
        $c = MailConfig::getSmtp();
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $c['host'] ?: 'localhost';
        $mail->Port = $c['port'] > 0 ? $c['port'] : 587;
        $user = $c['username'];
        $pass = $c['password'];
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPAuth = $user !== '' && $pass !== '';
        $enc = $c['encryption'] ?? 'tls';
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            $c['from_address'] !== '' ? $c['from_address'] : 'noreply@example.com',
            $c['from_name'] !== '' ? $c['from_name'] : 'Yve CRM'
        );

        return $mail;
    }
}
