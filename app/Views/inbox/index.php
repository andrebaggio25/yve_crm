<?php
$title = 'Inbox';
$pageTitle = 'Inbox WhatsApp';
?>
<div class="inbox-page -mx-4 flex min-h-[calc(100vh-7rem)] flex-col overflow-hidden sm:-mx-6 lg:-mx-8 lg:max-h-[calc(100vh-5rem)] lg:flex-row lg:rounded-xl lg:shadow-lg lg:ring-1 lg:ring-slate-200/80">
    <!-- Lista (estilo painel WA) -->
    <aside class="flex w-full flex-col border-b border-slate-200/90 bg-[#f0f2f5] lg:w-[320px] lg:min-w-[280px] lg:border-b-0 lg:border-r lg:border-slate-200/90">
        <div class="flex flex-col gap-2 border-b border-slate-200/80 bg-[#f0f2f5] p-3">
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 z-[1] -translate-y-1/2 text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.927 9.369l3.102 3.102a.75.75 0 0 0 1.06-1.06l-3.101-3.102A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" /></svg>
                </span>
                <input id="inbox-search" type="search" placeholder="Pesquisar ou começar nova conversa" class="w-full rounded-full border-0 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-800 shadow-sm ring-1 ring-slate-200/90 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30" />
            </div>
            <select id="inbox-filter" class="min-w-0 flex-1 rounded-full border-0 bg-white px-3 py-2.5 text-xs text-slate-700 shadow-sm ring-1 ring-slate-200/90 focus:outline-none focus:ring-2 focus:ring-primary-500/30 sm:text-sm">
                <option value="all">Todas</option>
                <option value="mine">Minhas</option>
                <option value="unassigned">Sem responsavel</option>
                <option value="closed">Fechadas</option>
            </select>
        </div>
        <div id="inbox-list" class="min-h-[200px] flex-1 space-y-0.5 overflow-y-auto bg-white p-1.5 lg:max-h-[calc(100vh-12rem)]">
            <p class="p-4 text-center text-sm text-slate-500">Carregando...</p>
        </div>
    </aside>

    <!-- Área do chat -->
    <section class="flex min-h-0 min-w-0 flex-1 flex-col bg-[#f0f2f5]">
        <header id="inbox-chat-header" class="flex items-center gap-3 border-b border-slate-200/80 bg-[#f0f2f5] px-3 py-2.5 sm:px-4">
            <span id="inbox-chat-avatar" class="flex-none"></span>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-[15px] font-semibold leading-tight text-slate-900" id="inbox-chat-title">Selecione uma conversa</h2>
                <p class="truncate text-xs text-slate-500" id="inbox-chat-sub"></p>
            </div>
        </header>

        <div id="inbox-messages" class="inbox-wa-chat-bg min-h-[240px] flex-1 space-y-1 overflow-y-auto px-2 py-3 sm:px-4 lg:max-h-[calc(100vh-14rem)]">
            <p class="rounded-lg bg-white/80 px-3 py-2 text-center text-sm text-slate-600 shadow-sm ring-1 ring-slate-200/60 backdrop-blur-sm">Escolha um lead na lista para conversar</p>
        </div>

        <footer class="border-t border-slate-200/80 bg-[#f0f2f5] px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 sm:px-3">
            <input type="file" id="inbox-file" class="hidden" accept="image/*,audio/*,video/*,.pdf,.doc,.docx,application/pdf" />
            <form id="inbox-composer" class="flex items-end gap-1.5 sm:gap-2">
                <button type="button" id="inbox-attach" title="Anexar" aria-label="Anexar arquivo" class="inbox-composer-icon mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-slate-600 transition-colors hover:bg-slate-200/90 disabled:opacity-40 sm:h-12 sm:w-12" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                    </svg>
                </button>
                <div class="relative min-h-[44px] min-w-0 flex-1 rounded-[1.35rem] bg-white shadow-[0_1px_0.5px_rgba(0,0,0,0.06)] ring-1 ring-slate-200/90 sm:min-h-[48px] sm:rounded-[1.5rem]">
                    <textarea id="inbox-text" rows="1" class="inbox-text-input max-h-32 min-h-[44px] w-full resize-none rounded-[1.35rem] border-0 bg-transparent px-3.5 py-2.5 pr-2 text-[15px] leading-snug text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 disabled:opacity-50 sm:min-h-[48px] sm:rounded-[1.5rem] sm:px-4 sm:py-3" placeholder="Mensagem" disabled></textarea>
                    <div id="inbox-recording-bar" class="hidden min-h-[44px] flex-col justify-center gap-1 px-3 py-2 sm:min-h-[48px]">
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
                <button type="submit" id="inbox-send" title="Enviar" aria-label="Enviar mensagem" class="mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white shadow-[0_1px_2px_rgba(0,0,0,0.12)] transition hover:bg-primary-700 disabled:hidden sm:h-12 sm:w-12" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6 translate-x-px">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
                <button type="button" id="inbox-mic" title="Gravar audio" aria-label="Gravar audio" class="inbox-composer-icon mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-slate-600 transition-colors hover:bg-slate-200/90 disabled:opacity-40 sm:h-12 sm:w-12" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                        <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="22"/>
                    </svg>
                </button>
            </form>
        </footer>
    </section>
</div>

<!-- Modal enviar mídia (estilo sheet WA) -->
<div id="inbox-media-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-[#0b141a]/55 backdrop-blur-[3px] sm:items-center sm:p-4" aria-hidden="true" role="dialog" aria-labelledby="inbox-media-modal-title">
    <div class="inbox-media-modal-panel flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-[1.25rem] bg-[#f0f2f5] sm:max-h-[88vh] sm:rounded-2xl">
        <!-- alça mobile -->
        <div class="flex shrink-0 justify-center bg-[#f0f2f5] pt-2 sm:hidden">
            <span class="h-1 w-10 rounded-full bg-slate-300/90"></span>
        </div>
        <div class="flex items-center justify-between gap-2 border-b border-slate-200/80 bg-[#f0f2f5] px-4 py-3 sm:rounded-t-2xl">
            <div class="min-w-0 flex-1">
                <h3 id="inbox-media-modal-title" class="text-[17px] font-semibold leading-tight text-slate-900">Enviar</h3>
                <p id="inbox-media-filename" class="mt-0.5 hidden truncate text-xs text-slate-500"></p>
            </div>
            <button type="button" id="inbox-media-dismiss" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/80" aria-label="Fechar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto bg-[#e5ddd5] p-4">
            <div id="inbox-media-preview" class="flex min-h-[180px] items-center justify-center overflow-hidden rounded-2xl bg-white/95 shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)] ring-1 ring-slate-200/60"></div>
            <label for="inbox-media-caption" class="mb-1.5 mt-4 block text-xs font-medium uppercase tracking-wide text-slate-500">Legenda</label>
            <textarea id="inbox-media-caption" rows="2" class="w-full resize-none rounded-2xl border-0 bg-white px-4 py-3 text-[15px] text-slate-900 shadow-sm ring-1 ring-slate-200/90 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/35" placeholder="Adicione uma legenda..."></textarea>
        </div>

        <div class="flex items-stretch gap-2 border-t border-slate-200/80 bg-[#f0f2f5] p-3 sm:rounded-b-2xl sm:p-4">
            <button type="button" id="inbox-media-cancel" class="h-12 flex-1 rounded-full text-[15px] font-medium text-primary-700 transition hover:bg-white/70">Cancelar</button>
            <button type="button" id="inbox-media-confirm" class="h-12 min-w-[120px] flex-[1.2] rounded-full bg-primary-600 text-[15px] font-semibold text-white shadow-[0_1px_2px_rgba(0,0,0,0.12)] transition hover:bg-primary-700">Enviar</button>
        </div>
    </div>
</div>

<?php $scripts = ['wavesurfer.min', 'audio-player', 'inbox']; ?>
