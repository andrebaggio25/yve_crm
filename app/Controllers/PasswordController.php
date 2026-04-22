<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Mail\MailService;
use App\Services\Security\LoginRateLimiter;

class PasswordController
{
    public function showForgot(Request $request, Response $response): void
    {
        if (Session::isAuthenticated()) {
            $response->redirect('/kanban');

            return;
        }
        $response->view('auth.forgot-password', ['title' => __('password.request_title')], null);
    }

    public function showReset(Request $request, Response $response): void
    {
        if (Session::isAuthenticated()) {
            $response->redirect('/kanban');

            return;
        }
        $token = (string) ($request->getParam('token') ?? '');
        if ($token === '') {
            $response->with('error', __('password.token_invalid'))->redirect('/password/forgot');

            return;
        }
        $response->view('auth.reset-password', [
            'title' => __('password.reset_title'),
            'token' => $token,
        ], null);
    }

    public function sendLink(Request $request, Response $response): void
    {
        Lang::initFromRequest();
        if (Session::isAuthenticated()) {
            $response->redirect('/kanban');

            return;
        }

        try {
            $data = $request->validate([
                'email' => 'required|email',
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->withErrors($errors)->withInput()->redirect('/password/forgot');

            return;
        }

        $ip = $request->getIp();
        if (LoginRateLimiter::isTooManyFromIp($ip)) {
            Session::flash('error', __('auth.rate_limited'));
            $response->redirect('/password/forgot');

            return;
        }

        LoginRateLimiter::recordGenericAttempt($ip);

        $user = Database::fetch(
            'SELECT id, email, name, locale FROM users WHERE email = :e AND deleted_at IS NULL AND status = :st',
            [':e' => $data['email'], ':st' => 'active']
        );

        if (!$user) {
            Session::flash('success', __('password.request_sent'));
            $response->redirect('/password/forgot');

            return;
        }

        $locale = in_array($user['locale'] ?? 'es', ['en', 'es', 'pt'], true) ? $user['locale'] : 'es';
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        Database::getInstance()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, created_at) VALUES (:uid, :th, :ex, NULL, NOW())'
        )->execute([':uid' => $user['id'], ':th' => $hash, ':ex' => $expires]);

        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        $link = $base . '/password/reset/' . rawurlencode($rawToken);

        [$subj, $html, $text] = self::resetEmailContent($locale, $link, (string) ($user['name'] ?? ''));

        if (MailService::isConfigured()) {
            MailService::queue((string) $user['email'], $subj, $html, $text, $locale, null, (string) ($user['name'] ?? ''));
        } else {
            App::log('[Password reset] MAIL not configured; link for ' . $user['email'] . ' (dev only): ' . $link);
        }

        Session::flash('success', __('password.request_sent'));
        $response->redirect('/password/forgot');
    }

    public function reset(Request $request, Response $response): void
    {
        Lang::initFromRequest();
        if (Session::isAuthenticated()) {
            $response->redirect('/kanban');

            return;
        }

        try {
            $data = $request->validate([
                'token' => 'required|min:10',
                'password' => 'required|min:8',
                'password_confirmation' => 'required|min:8',
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->withErrors($errors)->withInput()->back();

            return;
        }

        if ($data['password'] !== ($data['password_confirmation'] ?? '')) {
            Session::flash('error', __('password.mismatch'));
            $response->withInput()->back();

            return;
        }

        $hash = hash('sha256', $data['token']);
        $row = Database::fetch(
            "SELECT pr.*, u.id AS uid FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :h AND pr.used_at IS NULL AND pr.expires_at > NOW()",
            [':h' => $hash]
        );

        if (!$row) {
            Session::flash('error', __('password.token_invalid'));
            $response->redirect('/password/forgot');

            return;
        }

        $newHash = password_hash($data['password'], PASSWORD_BCRYPT);
        Database::getInstance()->prepare('UPDATE users SET password_hash = :p, updated_at = NOW() WHERE id = :id')->execute([
            ':p' => $newHash,
            ':id' => $row['uid'],
        ]);
        Database::getInstance()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')->execute([':id' => $row['id']]);

        Session::flash('success', __('password.reset_success'));
        $response->redirect('/login');
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function resetEmailContent(string $locale, string $link, string $name): array
    {
        $greet = $name !== '' ? $name : '';
        $messages = [
            'es' => [
                'subj' => 'Restablecer contraseña — Yve CRM',
                'html' => '<p>Hola' . ($greet ? ' ' . htmlspecialchars($greet) : '') . ',</p><p>Haga clic en el enlace para restablecer su contraseña (válido 1 hora):</p><p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p><p>Si no solicitó esto, ignore este mensaje.</p>',
                'text' => "Hola,\n\nRestablezca su contraseña con este enlace (1 hora):\n{$link}\n",
            ],
            'en' => [
                'subj' => 'Reset your password — Yve CRM',
                'html' => '<p>Hello' . ($greet ? ' ' . htmlspecialchars($greet) : '') . ',</p><p>Click the link to reset your password (valid 1 hour):</p><p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p><p>If you did not request this, ignore this email.</p>',
                'text' => "Hello,\n\nReset your password using this link (1 hour):\n{$link}\n",
            ],
            'pt' => [
                'subj' => 'Redefinir senha — Yve CRM',
                'html' => '<p>Olá' . ($greet ? ' ' . htmlspecialchars($greet) : '') . ',</p><p>Clique no link para redefinir sua senha (válido por 1 hora):</p><p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p><p>Se não solicitou, ignore.</p>',
                'text' => "Olá,\n\nRedefina sua senha com este link (1 hora):\n{$link}\n",
            ],
        ];
        $m = $messages[$locale] ?? $messages['es'];

        return [$m['subj'], $m['html'], $m['text']];
    }
}
