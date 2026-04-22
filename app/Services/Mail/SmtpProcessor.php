<?php

namespace App\Services\Mail;

use App\Core\Database;
use App\Core\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Procesa fila email_outbox (uso desde scheduled_worker o CLI).
 */
class SmtpProcessor
{
    public static function runBatch(int $limit = 25): array
    {
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

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $rawTid = $row['tenant_id'] ?? null;
            $tid = $rawTid !== null && $rawTid !== '' ? (int) $rawTid : null;

            if (!MailConfig::isReadyForOutboxRow($tid)) {
                $err = 'SMTP nao configurado: defina host e usuario (organizacao ou super admin / .env).';
                Database::getInstance()->prepare(
                    "UPDATE email_outbox SET status = 'failed', last_error = :e, attempts = attempts + 1 WHERE id = :id"
                )->execute([':e' => mb_substr($err, 0, 2000), ':id' => $id]);
                $failed++;
                continue;
            }

            $c = MailConfig::getSmtpForTenant($tid);
            $mailer = self::buildMailerFromConfig($c);

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

    /**
     * @param array{host:string,port:int,encryption:string,username:string,password:string,from_address:string,from_name:string} $c
     */
    public static function buildMailerFromConfig(array $c): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $c['host'] !== '' ? $c['host'] : 'localhost';
        $mail->Port = $c['port'] > 0 ? $c['port'] : 587;
        $user = $c['username'];
        $pass = $c['password'] ?? '';
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

        $t = (int) (Env::get('MAIL_TIMEOUT', '25') ?: 25);
        $mail->Timeout = $t > 0 && $t < 300 ? $t : 25;
        if (function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $mail->Timeout);
        }

        return $mail;
    }

    /**
     * Conecta e autentica (se SMTPAuth) e desliga — sem enviar mensagem.
     *
     * @param array{host:string,port:int,encryption:string,username:string,password:string,from_address:string,from_name:string} $c
     */
    public static function validateSmtpConfig(array $c): void
    {
        if (!MailConfig::isSystemSmtpComplete($c)) {
            throw new \InvalidArgumentException('Host e usuario SMTP sao obrigatorios para validar');
        }
        $mailer = self::buildMailerFromConfig($c);
        try {
            if (!$mailer->smtpConnect()) {
                throw new \RuntimeException('Falha ao conectar ou autenticar no SMTP');
            }
        } catch (MailException $e) {
            throw $e;
        } finally {
            $mailer->smtpClose();
        }
    }

    /**
     * Envio imediato (ex.: e-mail de teste). Propaga excecao PHPMailer.
     *
     * @param array{host:string,port:int,encryption:string,username:string,password:string,from_address:string,from_name:string} $c
     */
    public static function sendHtmlNow(
        array $c,
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $text
    ): void {
        $mailer = self::buildMailerFromConfig($c);
        $mailer->addAddress($toEmail, $toName);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $html;
        $mailer->AltBody = $text !== '' ? $text : strip_tags($html);
        $mailer->send();
    }
}
