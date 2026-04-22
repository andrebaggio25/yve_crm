<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Services\WhatsApp\ChatService;
use App\Services\WhatsApp\MediaStorageService;

class ChatController
{
    public function apiList(Request $request, Response $response): void
    {
        try {
            $filter = (string) ($request->get('filter') ?? 'all');
            App::log("[Chat] apiList - Filter: {$filter}");
            $svc = new ChatService();
            $conversations = $svc->listConversations($filter);
            App::log("[Chat] apiList - Conversas encontradas: " . count($conversations));
            $response->jsonSuccess(['conversations' => $conversations]);
        } catch (\Throwable $e) {
            App::logError('[Chat] apiList erro: ' . $e->getMessage(), $e);
            $response->jsonError('Erro ao listar conversas: ' . $e->getMessage(), 500);
        }
    }

    public function apiMessages(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);

            return;
        }
        try {
            $after = $request->get('after_id') ? (int) $request->get('after_id') : null;
            $svc = new ChatService();
            $svc->markRead($id);
            $response->jsonSuccess(['messages' => $svc->listMessages($id, $after)]);
        } catch (\Throwable $e) {
            App::logError('Chat messages', $e);
            $response->jsonError('Erro ao carregar mensagens', 500);
        }
    }

    public function apiSend(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        $body = $request->getJsonInput() ?? [];
        $text = trim((string) ($body['text'] ?? ''));
        if ($id <= 0 || $text === '') {
            $response->jsonError('Dados invalidos', 422);

            return;
        }
        try {
            $svc = new ChatService();
            $out = $svc->sendText($id, $text);
            if (!$out['ok']) {
                $response->jsonError($out['message'] ?? 'Erro ao enviar', 422);

                return;
            }
            $response->jsonSuccess($out, 'Enviado');
        } catch (\Throwable $e) {
            App::logError('Chat send', $e);
            $response->jsonError('Erro ao enviar', 500);
        }
    }

    public function apiRetryMessage(Request $request, Response $response): void
    {
        $cid = (int) ($request->getParam('id') ?? 0);
        $mid = (int) ($request->getParam('message_id') ?? 0);
        if ($cid <= 0 || $mid <= 0) {
            $response->jsonError('Dados invalidos', 422);

            return;
        }
        try {
            $svc = new ChatService();
            $out = $svc->retryFailedMessage($cid, $mid);
            if (!$out['ok']) {
                $response->jsonError($out['message'] ?? 'Erro ao reenviar', 422);

                return;
            }
            $response->jsonSuccess($out, 'Reenviado');
        } catch (\Throwable $e) {
            App::logError('Chat retry message', $e);
            $response->jsonError('Erro ao reenviar', 500);
        }
    }

    public function apiSendMedia(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);

            return;
        }
        if (!$request->hasFile('file')) {
            $response->jsonError('Arquivo obrigatorio', 422);

            return;
        }
        $file = $request->file('file');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $response->jsonError('Upload invalido', 422);

            return;
        }
        $max = (int) App::config('upload_max_size', 10 * 1024 * 1024);
        if ((int) ($file['size'] ?? 0) > $max) {
            $response->jsonError('Arquivo muito grande', 422);

            return;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $orig = (string) ($file['name'] ?? 'upload');
        if ($tmp === '') {
            $response->jsonError('Upload invalido', 422);

            return;
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $detected = finfo_file($f, $tmp);
                finfo_close($f);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }

        $caption = trim((string) $request->post('caption'));
        $vRaw = strtolower((string) $request->post('is_voice_note'));
        $isVoice = in_array($vRaw, ['1', 'true', 'on', 'yes'], true);
        $clientMime = trim((string) $request->post('client_mime'));

        $uploadOk = is_uploaded_file($tmp);
        App::log(
            '[InboxMedia] conv=' . $id
            . ' orig=' . $orig
            . ' size=' . (int) ($file['size'] ?? 0)
            . ' php_err=' . (int) ($file['error'] ?? -1)
            . ' is_uploaded_file=' . ($uploadOk ? '1' : '0')
            . ' finfo=' . $mime
            . ' client_mime=' . ($clientMime !== '' ? $clientMime : '-')
            . ' voice=' . ($isVoice ? '1' : '0')
        );

        if (!$uploadOk) {
            App::log('[InboxMedia] AVISO: is_uploaded_file=false — upload pode ser rejeitado pelo PHP/SAPI');
            $response->jsonError('Upload invalido (sessao/arquivo temporario)', 422);

            return;
        }

        $mime = MediaStorageService::refineDetectedMime($mime, $orig, $clientMime, $isVoice);
        App::log('[InboxMedia] mime_refinado=' . $mime);

        if (!MediaStorageService::mimeAllowed($mime)) {
            App::log('[InboxMedia] rejeitado mime nao permitido apos refinamento');
            $response->jsonError('Tipo de arquivo nao permitido', 422);

            return;
        }

        $bytes = file_get_contents($tmp);
        if ($bytes === false || $bytes === '') {
            $response->jsonError('Falha ao ler upload', 500);

            return;
        }

        $tid = (int) TenantContext::getEffectiveTenantId();
        try {
            $stored = MediaStorageService::store($tid, 'outbound', $bytes, $mime, $orig);
        } catch (\Throwable $e) {
            App::logError('MediaStorageService upload', $e);
            $response->jsonError($e->getMessage(), 422);

            return;
        }

        try {
            $svc = new ChatService();
            $out = $svc->sendMediaFromUpload($id, $stored['relative_path'], $caption, $isVoice, $mime, $orig);
            if (!$out['ok']) {
                App::log('[InboxMedia] ChatService retornou erro: ' . ($out['message'] ?? ''));
                $response->jsonError($out['message'] ?? 'Erro ao enviar', 422);

                return;
            }
            App::log('[InboxMedia] envio OK conv=' . $id . ' msg_id=' . (int) ($out['message_id'] ?? 0) . ' voice=' . ($isVoice ? '1' : '0'));
            $response->jsonSuccess($out, 'Enviado');
        } catch (\Throwable $e) {
            App::logError('Chat send media', $e);
            $response->jsonError('Erro ao enviar midia', 500);
        }
    }

    public function apiByLead(Request $request, Response $response): void
    {
        $leadId = (int) ($request->getParam('lead_id') ?? 0);
        if ($leadId <= 0) {
            $response->jsonError('Lead invalido', 400);

            return;
        }
        try {
            $svc = new ChatService();
            $conv = $svc->findByLeadId($leadId);
            $response->jsonSuccess(['conversation' => $conv]);
        } catch (\Throwable $e) {
            App::logError('Chat by lead', $e);
            $response->jsonError('Erro', 500);
        }
    }
}
