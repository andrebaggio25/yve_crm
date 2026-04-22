<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantAwareDatabase;
use App\Services\WhatsApp\MediaStorageService;

class MediaController
{
    public function apiMessageMedia(Request $request, Response $response): void
    {
        Session::start();
        if (!Session::isAuthenticated()) {
            $response->jsonError('Nao autorizado', 401);

            return;
        }

        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);

            return;
        }

        $row = TenantAwareDatabase::fetch(
            'SELECT m.id, m.type, m.media_local_path, m.media_mime_type, m.media_filename
             FROM messages m
             INNER JOIN conversations c ON c.id = m.conversation_id AND c.tenant_id = m.tenant_id
             WHERE m.id = :id AND m.tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );
        if (!$row || empty($row['media_local_path'])) {
            $response->jsonError('Midia nao encontrada', 404);

            return;
        }

        $rel = (string) $row['media_local_path'];
        $abs = MediaStorageService::absolutePathForRelative($rel);
        $base = realpath(App::storagePath('media'));
        $real = realpath($abs);
        if ($base === false || $real === false || !str_starts_with($real, $base)) {
            App::log('[MediaController] bloqueio path: ' . $rel);

            $response->jsonError('Midia nao encontrada', 404);

            return;
        }

        $mime = (string) ($row['media_mime_type'] ?? 'application/octet-stream');
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $fname = (string) ($row['media_filename'] ?? '');
        if ($fname === '') {
            $fname = basename($real);
        }

        $type = (string) ($row['type'] ?? 'document');
        $disp = ($type === 'document') ? 'attachment' : 'inline';

        $response->file($real, $mime, $disp, $fname);
    }
}
