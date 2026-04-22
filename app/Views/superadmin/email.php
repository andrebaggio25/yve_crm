<?php
$title = 'E-mail (fila)';
$pageTitle = 'Fila e teste de e-mail';
$scripts = ['superadmin-email'];
?>
<div class="space-y-6">
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-medium">Dica</p>
        <p class="mt-1">E-mails com destinatarios reais (reset de senha, etc.) sao processados pelo worker/cron. O botao "Teste" envia imediatamente via SMTP do sistema. Verifique a fila abaixo se algo ficou <span class="font-mono">pending</span> ou <span class="font-mono">failed</span>.</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-base font-semibold text-slate-900">E-mail de teste (SMTP global)</h2>
        <p class="mb-3 text-sm text-slate-600">Usa a mesma configuracao de <a class="text-primary-600 underline" href="/superadmin/settings">Configuracoes do sistema</a> (SMTP) ou .env.</p>
        <form id="form-sa-test" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label class="text-xs font-medium text-slate-700" for="sa-test-to">Enviar para</label>
                <input type="email" name="to" id="sa-test-to" required placeholder="seu@email.com" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" autocomplete="email">
            </div>
            <button type="submit" class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Enviar teste</button>
        </form>
        <p id="sa-test-msg" class="mt-2 hidden text-sm"></p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-base font-semibold text-slate-900">Fila global</h2>
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs text-slate-600">Tenant</label>
                <input type="text" id="outbox-filter-tenant" placeholder="id ou vazio = todos" class="w-32 rounded border border-slate-200 px-2 py-1 text-sm" title="ID numerico, ou deixe vazio, ou escreva system">
                <label class="text-xs text-slate-600">Status</label>
                <select id="outbox-filter-status" class="rounded border border-slate-200 px-2 py-1 text-sm">
                    <option value="">Todos</option>
                    <option value="pending">pending</option>
                    <option value="sending">sending</option>
                    <option value="sent">sent</option>
                    <option value="failed">failed</option>
                </select>
                <label class="flex items-center gap-1 text-xs text-slate-600">
                    <input type="checkbox" id="outbox-only-system" class="rounded"> So sistema
                </label>
                <button type="button" id="outbox-refresh" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Atualizar</button>
            </div>
        </div>
        <div id="sa-outbox" class="overflow-x-auto text-sm">
            <p class="text-slate-500">Carregando...</p>
        </div>
    </div>
</div>
