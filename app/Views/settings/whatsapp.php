<?php
$title = 'WhatsApp';
$pageTitle = 'Integracao WhatsApp';
$scripts = ['whatsapp-settings'];
?>

<div class="mx-auto max-w-2xl space-y-6">

    <!-- Status da Integracao -->
    <div id="global-status" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3">
            <div id="global-icon" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100">
                <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <h2 class="font-semibold text-slate-900">Status da Integracao</h2>
                <p id="global-text" class="text-sm text-slate-500">Verificando...</p>
            </div>
            <span id="global-badge" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">-</span>
        </div>
    </div>

    <!-- Botao Ativar (quando nao existe instancia) -->
    <div id="activate-card" class="hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <h3 class="mt-3 font-semibold text-slate-900">Ativar WhatsApp</h3>
            <p class="mt-1 text-sm text-slate-500">
                A integracao esta pronta. Clique abaixo para criar sua instancia automaticamente.
            </p>
            <p class="mt-2 text-xs text-slate-400">
                A instancia sera criada com o nome: <code id="instance-name-preview" class="rounded bg-slate-100 px-1">carregando...</code>
            </p>
            <button type="button" id="btn-activate" class="mt-4 rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                Ativar WhatsApp
            </button>
            <div id="activate-feedback" class="mt-2 hidden text-sm"></div>
        </div>
    </div>

    <!-- Estado da Conexao (quando configurado) -->
    <div id="connection-card" class="hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div id="status-icon" class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <div id="status-title" class="font-semibold text-slate-900">Desconectado</div>
                    <div id="status-desc" class="text-sm text-slate-500">Nenhum numero conectado</div>
                </div>
            </div>
            <button type="button" id="btn-refresh" class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50" title="Atualizar status">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>

        <!-- Instancia Info -->
        <div class="mt-4 rounded-lg border border-slate-100 bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Instancia na Evolution API:</div>
            <div id="instance-name" class="font-mono text-sm font-medium text-slate-700">-</div>
        </div>

        <!-- Numero conectado -->
        <div id="phone-info" class="mt-4 hidden rounded-lg border border-green-200 bg-green-50 p-3">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <div>
                    <div class="text-xs text-green-700">Numero conectado</div>
                    <div id="phone-number" class="font-mono text-sm font-semibold text-green-800">-</div>
                </div>
            </div>
        </div>

        <!-- QR Code -->
        <div id="qr-section" class="mt-4 hidden">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center">
                <p class="mb-3 text-sm text-amber-800">Escaneie o QR Code com seu WhatsApp para conectar</p>
                <div id="qr-container" class="flex justify-center">
                    <!-- QR Code sera inserido aqui -->
                </div>
                <p class="mt-2 text-xs text-amber-600">Abra o WhatsApp &gt; Menu &gt; Aparelhos conectados &gt; Conectar aparelho</p>

                <!-- Codigo de pareamento (se disponivel) -->
                <div id="pairing-code-section" class="mt-3 hidden">
                    <div class="text-xs text-amber-700">Ou use o codigo de pareamento:</div>
                    <div id="pairing-code" class="mt-1 font-mono text-lg font-bold tracking-widest text-amber-900">-</div>
                </div>

                <button type="button" id="btn-new-qr" class="mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Gerar novo QR Code
                </button>
            </div>
        </div>

        <!-- Acoes -->
        <div id="connection-actions" class="mt-4 flex gap-2">
            <button type="button" id="btn-connect" class="hidden rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                Conectar Numero
            </button>
            <button type="button" id="btn-disconnect" class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                Desconectar
            </button>
        </div>
    </div>

    <!-- Informacoes do Webhook -->
    <div id="webhook-info" class="hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 flex items-center gap-2 font-semibold text-slate-900">
            <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            Webhook Configurado
        </h3>
        <p class="text-sm text-slate-600">Esta instancia esta configurada para receber webhooks da Evolution API.</p>
        <div class="mt-3 rounded-lg bg-slate-50 p-3">
            <div class="text-xs text-slate-500">URL do webhook:</div>
            <code id="webhook-url" class="mt-1 block break-all text-xs font-mono text-slate-700">
                <?= htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? 'seu-dominio.com')) ?>/webhook/evolution/{token}
            </code>
        </div>
        <p class="mt-2 text-xs text-slate-500">
            Certifique-se de que esta URL esteja configurada corretamente no painel da Evolution API.
        </p>
    </div>

</div>
