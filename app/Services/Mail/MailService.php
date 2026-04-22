<?php

namespace App\Services\Mail;

use App\Core\Database;
use App\Core\Env;

class MailService
{
    public static function queue(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        string $locale = 'es',
        ?int $tenantId = null,
        ?string $toName = null
    ): int {
        $st = Database::getInstance()->prepare(
            'INSERT INTO email_outbox (tenant_id, to_email, to_name, subject, body_html, body_text, locale, status, attempts, created_at)
             VALUES (:tid, :toe, :ton, :subj, :html, :txt, :loc, :status, 0, NOW())'
        );
        $st->execute([
            ':tid' => $tenantId,
            ':toe' => $toEmail,
            ':ton' => $toName,
            ':subj' => $subject,
            ':html' => $bodyHtml,
            ':txt' => $bodyText,
            ':loc' => in_array($locale, ['en', 'es', 'pt'], true) ? $locale : 'es',
            ':status' => 'pending',
        ]);

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function isConfigured(): bool
    {
        return Env::get('MAIL_HOST', '') !== '' && Env::get('MAIL_USERNAME', '') !== '';
    }
}
