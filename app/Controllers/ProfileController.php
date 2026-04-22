<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class ProfileController
{
    public function index(Request $request, Response $response): void
    {
        $u = Session::user();
        if (!$u) {
            $response->redirect('/login');

            return;
        }
        $user = Database::fetch(
            'SELECT id, name, email, role, phone, avatar_url, locale, status FROM users WHERE id = :id AND deleted_at IS NULL',
            [':id' => $u['id']]
        );
        if (!$user) {
            $response->redirect('/login');

            return;
        }

        $response->view('profile.index', [
            'title' => __('profile.title'),
            'pageTitle' => __('profile.page_title'),
            'user' => $user,
        ]);
    }

    public function update(Request $request, Response $response): void
    {
        Lang::initFromRequest();
        $u = Session::user();
        if (!$u) {
            $response->redirect('/login');

            return;
        }

        $locale = $request->input('locale');
        if (!in_array($locale, ['en', 'es', 'pt'], true)) {
            $locale = 'es';
        }

        $update = ['locale' => $locale, 'updated_at' => date('Y-m-d H:i:s')];

        if (!empty($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'] ?? '')) {
            $file = $_FILES['avatar'];
            $max = (int) App::config('upload_max_size', 10 * 1024 * 1024);
            if (($file['size'] ?? 0) > $max) {
                Session::flash('error', 'File too large.');
                $response->redirect('/profile');

                return;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']) ?: '';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                Session::flash('error', 'Invalid image type.');
                $response->redirect('/profile');

                return;
            }
            $ext = $allowed[$mime];
            $dir = App::publicPath('uploads/avatars');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = 'u' . (int) $u['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $dir . '/' . $name;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                Session::flash('error', 'Upload failed.');
                $response->redirect('/profile');

                return;
            }
            $publicPath = '/uploads/avatars/' . $name;
            $update['avatar_url'] = $publicPath;
        }

        Database::update('users', $update, 'id = :id', [':id' => $u['id']]);

        $fresh = Database::fetch('SELECT * FROM users WHERE id = :id', [':id' => $u['id']]);
        if ($fresh) {
            unset($fresh['password_hash']);
            Session::set('user', $fresh);
        }

        Lang::setLocale($locale);
        Session::flash('success', __('profile.saved'));
        $response->redirect('/profile');
    }
}
