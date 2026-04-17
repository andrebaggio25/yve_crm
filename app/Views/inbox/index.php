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
        <footer class="border-t border-slate-200 bg-[#f0f2f5] p-2 sm:p-3">
            <input type="file" id="inbox-file" class="hidden" accept="image/*,audio/*,video/*,.pdf,.doc,.docx,application/pdf" />
            <form id="inbox-composer" class="flex items-end gap-2">
                <button type="button" id="inbox-attach" title="Anexar" aria-label="Anexar arquivo" class="inbox-composer-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-slate-600 hover:bg-slate-200/80 disabled:opacity-40" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7"><path fill-rule="evenodd" d="M18.97 3.659a2.25 2.25 0 0 0-3.182 0l-10.94 10.94a4.5 4.5 0 0 0 6.364 6.364l7.5-7.5a.75.75 0 1 0-1.06-1.06l-7.5 7.5a3 3 0 1 1-4.243-4.243l10.939-10.94a.75.75 0 0 1 1.061 0l3.182 3.182a.75.75 0 0 1-1.06 1.061l-10.94 10.94a1.5 1.5 0 0 0 2.122 2.122l7.5-7.5a.75.75 0 1 0-1.06-1.06l-7.5 7.5a.75.75 0 0 1-1.061 0Z" clip-rule="evenodd" /></svg>
                </button>
                <div class="relative min-h-[48px] min-w-0 flex-1 rounded-[1.5rem] bg-white shadow-sm ring-1 ring-slate-200/80">
                    <textarea id="inbox-text" rows="1" class="inbox-text-input max-h-32 min-h-[48px] w-full resize-none rounded-[1.5rem] border-0 bg-transparent px-4 py-3 pr-3 text-sm leading-snug focus:outline-none focus:ring-0 disabled:opacity-50" placeholder="Mensagem" disabled></textarea>
                    <div id="inbox-recording-bar" class="hidden min-h-[48px] flex-col justify-center gap-1 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span id="inbox-rec-pulse" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-red-600"></span>
                            </span>
                            <span id="inbox-rec-timer" class="font-mono text-sm font-medium tabular-nums text-slate-800">0:00</span>
                            <div class="inbox-rec-wave flex flex-1 items-end justify-center gap-0.5 px-1">
                                <span class="inbox-rec-bar h-2 w-1 rounded-sm bg-primary-500"></span>
                                <span class="inbox-rec-bar h-4 w-1 rounded-sm bg-primary-500"></span>
                                <span class="inbox-rec-bar h-3 w-1 rounded-sm bg-primary-500"></span>
                                <span class="inbox-rec-bar h-5 w-1 rounded-sm bg-primary-500"></span>
                                <span class="inbox-rec-bar h-2 w-1 rounded-sm bg-primary-500"></span>
                            </div>
                        </div>
                        <p class="text-center text-[11px] text-slate-500">Toque no microfone para parar · <button type="button" id="inbox-rec-cancel" class="font-medium text-red-600 underline">Cancelar</button></p>
                    </div>
                </div>
                <button type="submit" id="inbox-send" title="Enviar" aria-label="Enviar mensagem" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white shadow-md hover:bg-primary-700 disabled:hidden" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.167.75.75 0 0 0 0-1.5A60.517 60.517 0 0 0 3.478 2.404Z" /></svg>
                </button>
                <button type="button" id="inbox-mic" title="Gravar audio" aria-label="Gravar audio" class="inbox-composer-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-slate-600 hover:bg-slate-200/80 disabled:opacity-40" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7"><path d="M8.25 4.5a3.75 3.75 0 1 1 7.5 0v6a3.75 3.75 0 1 1-7.5 0v-6ZM15 10.5a3 3 0 1 1-6 0v-6a3 3 0 0 1 6 0v6Z" /><path d="M3.75 10.5a.75.75 0 0 0 0 1.5h1.37a6.75 6.75 0 0 0 12.76 0h1.37a.75.75 0 0 0 0-1.5h-1.37a6.75 6.75 0 0 0-12.76 0H3.75Z" /></svg>
                </button>
            </form>
        </footer>
    </section>
</div>

<div id="inbox-media-modal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm" aria-hidden="true" role="dialog" aria-labelledby="inbox-media-modal-title">
    <div class="flex h-full w-full items-end justify-center p-0 sm:items-center sm:p-4">
        <div class="flex max-h-[90dvh] w-full max-w-md flex-col rounded-t-3xl bg-white shadow-2xl sm:max-h-[85vh] sm:rounded-3xl">
            <div class="flex items-center justify-between border-b border-slate-100 bg-white px-4 py-3 sm:rounded-t-3xl">
                <h3 id="inbox-media-modal-title" class="text-base font-semibold text-slate-900">Enviar</h3>
                <button type="button" id="inbox-media-dismiss" class="flex h-8 w-8 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-slate-100" aria-label="Fechar">&times;</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50/50 p-4">
                <div id="inbox-media-preview" class="mb-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"></div>
                <label for="inbox-media-caption" class="mb-1.5 block text-xs font-medium text-slate-600">Legenda (opcional)</label>
                <textarea id="inbox-media-caption" rows="2" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="Adicione uma legenda..."></textarea>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 bg-white p-4 sm:rounded-b-3xl">
                <button type="button" id="inbox-media-cancel" class="h-11 rounded-full px-5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancelar</button>
                <button type="button" id="inbox-media-confirm" class="h-11 min-w-[100px] rounded-full bg-primary-600 px-5 text-sm font-semibold text-white shadow-md hover:bg-primary-700">Enviar</button>
            </div>
        </div>
    </div>
</div>

<?php $scripts = ['wavesurfer.min', 'audio-player', 'inbox']; ?>
