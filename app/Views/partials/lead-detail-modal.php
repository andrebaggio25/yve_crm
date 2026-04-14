<?php
/** Modal de detalhe do lead: fora do main para cobrir header/nav e scroll correto. */
?>
<div id="lead-detail-modal" class="fixed inset-0 hidden overflow-y-auto overscroll-contain" aria-hidden="true">
    <div class="fixed inset-0 min-h-full bg-slate-900/60 backdrop-blur-sm transition-opacity" id="lead-detail-backdrop" aria-hidden="true"></div>
    <div class="relative z-10 flex min-h-full items-end justify-center px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-4 sm:items-center sm:p-5 sm:pb-5">
        <div
            id="lead-detail-panel"
            class="flex w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 sm:rounded-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="lead-detail-title"
            onclick="event.stopPropagation()"
        >
            <div class="shrink-0 bg-gradient-to-br from-primary-600 via-primary-600 to-primary-800 px-4 py-3 text-white sm:px-5 sm:py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-primary-100/90">Lead</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight tracking-tight sm:text-2xl" id="lead-detail-title">Carregando...</h3>
                        <p class="mt-1.5 text-sm text-primary-100/95" id="lead-detail-subtitle"></p>
                    </div>
                    <button type="button" class="shrink-0 rounded-xl bg-white/10 p-2.5 text-white transition hover:bg-white/20" id="lead-detail-close" aria-label="Fechar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="lead-detail-toolbar" class="mt-3 hidden flex flex-wrap gap-1.5 border-t border-white/15 pt-3 sm:gap-2 sm:pt-3">
                    <button
                        type="button"
                        id="lead-detail-act-wa"
                        class="hidden inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25 sm:text-sm"
                        title="Usa template da etapa e registra no historico"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.35-8.413"/></svg>
                        WhatsApp rapido
                    </button>
                    <a
                        href="#"
                        id="lead-detail-act-mail"
                        class="hidden inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25 sm:text-sm"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        E-mail
                    </a>
                    <a
                        href="#"
                        id="lead-detail-act-tel"
                        class="hidden inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25 sm:text-sm"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        Ligar
                    </a>
                    <button
                        type="button"
                        id="lead-detail-act-copy-phone"
                        class="hidden inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25 sm:text-sm"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Copiar telefone
                    </button>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50/90" id="lead-detail-body">
                <div class="flex justify-center py-16">
                    <div class="spinner h-10 w-10 rounded-full border-2 border-slate-200 border-t-primary-600"></div>
                </div>
            </div>
        </div>
    </div>
</div>
