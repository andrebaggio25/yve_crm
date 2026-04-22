<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\Mail\MailConfig;
use App\Services\Mail\SmtpProcessor;

class EmailDiagnosticController
{
    public function pageSuperAdmin(Request $request, Response $response): void
    {
        $response->view('superadmin.email', [
            'title' => 'E-mail (fila)',
            'pageTitle' => 'Fila e teste de e-mail',
        ]);
    }

    /**
     * Fila: apenas o tenant do contexto (admin).
     */
    public function apiTenantOutbox(Request $request, Response $response): void
    {
        $user = Session::user();
        $tid = TenantContext::getTenantId() ?? (int) ($user['tenant_id'] ?? 0);
        if ($tid <= 0) {
            $response->jsonError('Sem organizacao', 400);

            return;
        }

        $limit = min(100, max(10, (int) $request->get('limit', 50)));
        $status = (string) $request->get('status', '');

        $where = 'tenant_id = :tid';
        $params = [':tid' => $tid];
        if (in_array($status, ['pending', 'sending', 'sent', 'failed'], true)) {
            $where .= ' AND status = :st';
            $params[':st'] = $status;
        }

        try {
            $rows = Database::fetchAll(
                "SELECT o.id, o.tenant_id, o.to_email, o.to_name, o.subject, o.status, o.attempts, o.last_error, o.locale, o.created_at, o.sent_at
                 FROM email_outbox o
                 WHERE {$where}
                 ORDER BY o.id DESC
                 LIMIT {$limit}",
                $params
            );
            $response->jsonSuccess(['items' => $this->mapOutboxRows($rows)]);
        } catch (\Throwable $e) {
            $response->jsonError('Erro ao listar', 500);
        }
    }

    /**
     * Fila global (super admin): todos os tenants e tenant_id NULL (sistema).
     */
    public function apiSuperAdminOutbox(Request $request, Response $response): void
    {
        $limit = min(200, max(10, (int) $request->get('limit', 80)));
        $status = (string) $request->get('status', '');
        $tenantFilter = $request->get('tenant_id', '');

        $where = ['1=1'];
        $params = [];

        if ($tenantFilter === 'system' || $tenantFilter === 'null') {
            $where[] = 'o.tenant_id IS NULL';
        } elseif ($tenantFilter !== null && $tenantFilter !== '') {
            $where[] = 'o.tenant_id = :ftid';
            $params[':ftid'] = (int) $tenantFilter;
        }

        if (in_array($status, ['pending', 'sending', 'sent', 'failed'], true)) {
            $where[] = 'o.status = :st';
            $params[':st'] = $status;
        }

        $w = implode(' AND ', $where);
        $lim = (int) $limit;

        try {
            $rows = Database::fetchAll(
                "SELECT o.id, o.tenant_id, o.to_email, o.to_name, o.subject, o.status, o.attempts, o.last_error, o.locale, o.created_at, o.sent_at, t.name AS tenant_name, t.slug AS tenant_slug
                 FROM email_outbox o
                 LEFT JOIN tenants t ON t.id = o.tenant_id
                 WHERE {$w}
                 ORDER BY o.id DESC
                 LIMIT {$lim}",
                $params
            );
            $response->jsonSuccess(['items' => $this->mapOutboxRows($rows)]);
        } catch (\Throwable $e) {
            $response->jsonError('Erro ao listar', 500);
        }
    }

    public function apiTenantTest(Request $request, Response $response): void
    {
        $this->setSmtpTestLimits();
        $data = $request->getJsonInput() ?? [];
        $to = trim((string) ($data['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $response->jsonError('E-mail destino invalido', 422);

            return;
        }

        $user = Session::user();
        $tid = TenantContext::getTenantId() ?? (int) ($user['tenant_id'] ?? 0);
        if ($tid <= 0) {
            $response->jsonError('Sem organizacao', 400);

            return;
        }

        $c = MailConfig::getSmtpForTenant($tid);
        if (!MailConfig::isSystemSmtpComplete($c)) {
            $response->jsonError('SMTP nao configurado. Defina em Organizacao ou use o padrao do sistema.', 422);

            return;
        }

        $name = (string) ($user['name'] ?? 'Admin');
        $html = '<p>' . htmlspecialchars('E-mail de teste enviado pela organizacao (Yve CRM).', ENT_QUOTES, 'UTF-8') . '</p>';
        $text = "E-mail de teste (Yve CRM) para {$to}";

        try {
            SmtpProcessor::sendHtmlNow(
                $c,
                $to,
                $name,
                'Teste de e-mail — Yve CRM',
                $html,
                $text
            );
            $response->jsonSuccess([], 'E-mail de teste enviado. Verifique a caixa de entrada (e spam).');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $response->jsonError('Falha ao enviar: ' . mb_substr($msg, 0, 500), 502);
        }
    }

    /**
     * Valida conexao SMTP (tenant) com dados do formulario; senha vazia usa a gravada.
     */
    public function apiTenantValidateSmtp(Request $request, Response $response): void
    {
        $this->setSmtpTestLimits();
        $data = $request->getJsonInput() ?? [];
        $user = Session::user();
        $tid = TenantContext::getTenantId() ?? (int) ($user['tenant_id'] ?? 0);
        if ($tid <= 0) {
            $response->jsonError('Sem organizacao', 400);

            return;
        }
        $row = Database::fetch('SELECT settings_json FROM tenants WHERE id = :id', [':id' => $tid]);
        $merged = [];
        if ($row && !empty($row['settings_json'])) {
            $decoded = is_string($row['settings_json']) ? json_decode($row['settings_json'], true) : $row['settings_json'];
            $merged = is_array($decoded) ? $decoded : [];
        }
        if (trim((string) ($data['smtp_password'] ?? '')) === '' && !empty($merged['smtp_password'])) {
            $data['smtp_password'] = (string) $merged['smtp_password'];
        }
        if (trim((string) ($data['smtp_password'] ?? '')) === '' && (string) Env::get('MAIL_PASSWORD', '') !== '') {
            $data['smtp_password'] = (string) Env::get('MAIL_PASSWORD', '');
        }
        $base = MailConfig::getSmtpForTenant($tid);
        $c = MailConfig::applySmtpOverrides($data, $base);
        if (!MailConfig::isSystemSmtpComplete($c)) {
            $response->jsonError('Preencha host, usuario e senha (ou a ja salva / .env).', 422);

            return;
        }
        try {
            SmtpProcessor::validateSmtpConfig($c);
            $response->jsonSuccess([], 'Conexao SMTP validada (conexao e autenticacao).');
        } catch (\Throwable $e) {
            $response->jsonError('Validacao falhou: ' . mb_substr($e->getMessage(), 0, 450), 502);
        }
    }

    public function apiSuperAdminTest(Request $request, Response $response): void
    {
        $this->setSmtpTestLimits();
        $data = $request->getJsonInput() ?? [];
        $to = trim((string) ($data['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $response->jsonError('E-mail destino invalido', 422);

            return;
        }

        $c = MailConfig::getSmtp();
        if (!MailConfig::isSystemSmtpComplete($c)) {
            $response->jsonError('SMTP global nao configurado (super admin / .env).', 422);

            return;
        }

        $html = '<p>' . htmlspecialchars('E-mail de teste do sistema (Yve CRM).', ENT_QUOTES, 'UTF-8') . '</p>';
        $text = "E-mail de teste (Yve CRM - sistema) para {$to}";

        try {
            SmtpProcessor::sendHtmlNow(
                $c,
                $to,
                '',
                'Teste de e-mail (sistema) — Yve CRM',
                $html,
                $text
            );
            $response->jsonSuccess([], 'E-mail de teste enviado. Verifique a caixa de entrada (e spam).');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $response->jsonError('Falha ao enviar: ' . mb_substr($msg, 0, 500), 502);
        }
    }

    private function setSmtpTestLimits(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function mapOutboxRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $err = (string) ($r['last_error'] ?? '');
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'tenant_id' => $r['tenant_id'] !== null && $r['tenant_id'] !== '' ? (int) $r['tenant_id'] : null,
                'tenant_name' => $r['tenant_name'] ?? null,
                'tenant_slug' => $r['tenant_slug'] ?? null,
                'to_email' => (string) ($r['to_email'] ?? ''),
                'to_name' => $r['to_name'] ?? null,
                'subject' => (string) ($r['subject'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'attempts' => (int) ($r['attempts'] ?? 0),
                'last_error' => $err === '' ? null : (mb_strlen($err) > 500 ? mb_substr($err, 0, 500) . '...' : $err),
                'locale' => (string) ($r['locale'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'sent_at' => $r['sent_at'] !== null && $r['sent_at'] !== '' ? (string) $r['sent_at'] : null,
            ];
        }

        return $out;
    }
}
