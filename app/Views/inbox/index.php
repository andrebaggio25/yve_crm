<?php
$title = 'Inbox';
$pageTitle = 'Inbox WhatsApp';
?>
<div class="inbox-page -mx-4 flex min-h-[calc(100vh-7rem)] flex-col overflow-hidden sm:-mx-6 lg:-mx-8 lg:max-h-[calc(100vh-5rem)] lg:flex-row">
    <aside class="flex w-full flex-col border-b border-slate-200 bg-white lg:w-80 lg:min-w-[280px] lg:border-b-0 lg:border-r">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-3">
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.927 9.369l3.102 3.102a.75.75 0 0 0 1.06-1.06l-3.101-3.102A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" /></svg>
                </span>
                <input id="inbox-search" type="search" placeholder="Buscar conversa..." class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20" />
            </div>
            <select id="inbox-filter" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs sm:text-sm">
                <option value="all">Todas</option>
                <option value="mine">Minhas</option>
                <option value="unassigned">Sem responsavel</option>
                <option value="closed">Fechadas</option>
            </select>
        </div>
        <div id="inbox-list" class="min-h-[200px] flex-1 space-y-1 overflow-y-auto p-2 lg:max-h-[calc(100vh-12rem)]">
            <p class="p-4 text-center text-sm text-slate-500">Carregando...</p>
        </div>
    </aside>
    <section class="flex min-h-0 min-w-0 flex-1 flex-col bg-slate-50">
        <header id="inbox-chat-header" class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
            <span id="inbox-chat-avatar" class="flex-none"></span>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-sm font-semibold text-slate-900" id="inbox-chat-title">Selecione uma conversa</h2>
                <p class="truncate text-xs text-slate-500" id="inbox-chat-sub"></p>
            </div>
        </header>
        <div id="inbox-messages" class="min-h-[240px] flex-1 space-y-2 overflow-y-auto p-4 lg:max-h-[calc(100vh-14rem)]">
            <p class="text-center text-sm text-slate-500">Escolha um lead na lista</p>
        </div>
        <footer class="border-t border-slate-200 bg-white p-3">
            <form id="inbox-composer" class="flex gap-2">
                <textarea id="inbox-text" rows="2" class="min-w-0 flex-1 resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="Mensagem..." disabled></textarea>
                <button type="submit" class="shrink-0 self-end rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" disabled id="inbox-send">Enviar</button>
            </form>
        </footer>
    </section>
</div>

<?php $scripts = ['inbox']; ?>
