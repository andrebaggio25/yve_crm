<?php
$title = $automationId > 0 ? 'Editar Automacao' : 'Nova Automacao';
$pageTitle = $automationId > 0 ? 'Editar Fluxo' : 'Novo Fluxo';
$automationId = $automationId ?? 0;
?>
<!-- Header do builder -->
<div class="bg-white border-b border-slate-200 -mx-4 -mt-4 px-4 py-3 mb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/settings/automations" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <input type="text" id="flow-name" placeholder="Nome do fluxo" class="text-lg font-semibold text-slate-900 bg-transparent border-none focus:ring-0 p-0 placeholder-slate-400">
                <input type="text" id="flow-description" placeholder="Descricao opcional" class="block text-sm text-slate-500 bg-transparent border-none focus:ring-0 p-0 placeholder-slate-400">
            </div>
        </div>
        <div class="flex items-center gap-3">
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" id="flow-active" class="sr-only peer" checked>
                <div class="h-6 w-11 rounded-full bg-slate-200 peer-checked:bg-primary-600 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform translate-x-5" id="flow-active-knob"></div>
                <span class="ml-2 text-sm font-medium text-slate-700">Ativo</span>
            </label>
            <button type="button" id="btn-save" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Conteudo principal -->
<div class="flex" style="min-height: calc(100vh - 200px);">
    <!-- Sidebar de nodes -->
    <div class="w-64 flex-shrink-0 border-r border-slate-200 bg-slate-50 overflow-y-auto hidden md:block">
        <div class="p-4 space-y-6">
            <!-- Triggers -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Gatilhos</h3>
                <div class="space-y-2">
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="trigger" data-subtype="lead_created">
                        <div class="h-8 w-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Lead criado</p>
                            <p class="text-xs text-slate-500 truncate">Quando novo lead entra</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="trigger" data-subtype="lead_stage_changed">
                        <div class="h-8 w-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Mudanca de etapa</p>
                            <p class="text-xs text-slate-500 truncate">Lead muda de etapa</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="trigger" data-subtype="whatsapp_message_received">
                        <div class="h-8 w-8 rounded-lg bg-green-100 text-green-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Mensagem WhatsApp</p>
                            <p class="text-xs text-slate-500 truncate">Recebe mensagem</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="trigger" data-subtype="tag_added">
                        <div class="h-8 w-8 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Tag adicionada</p>
                            <p class="text-xs text-slate-500 truncate">Lead recebe tag</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Condicoes -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Condicoes</h3>
                <div class="space-y-2">
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="condition" data-subtype="stage_is">
                        <div class="h-8 w-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Etapa e</p>
                            <p class="text-xs text-slate-500 truncate">Verifica etapa atual</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="condition" data-subtype="has_tag">
                        <div class="h-8 w-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Tem tag</p>
                            <p class="text-xs text-slate-500 truncate">Verifica tag do lead</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="condition" data-subtype="message_contains">
                        <div class="h-8 w-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Mensagem contem</p>
                            <p class="text-xs text-slate-500 truncate">Palavra-chave na msg</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Acoes -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Acoes</h3>
                <div class="space-y-2">
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="action" data-subtype="move_stage">
                        <div class="h-8 w-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Mover etapa</p>
                            <p class="text-xs text-slate-500 truncate">Altera etapa do lead</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="action" data-subtype="add_tag">
                        <div class="h-8 w-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Adicionar tag</p>
                            <p class="text-xs text-slate-500 truncate">Tag ao lead</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="action" data-subtype="remove_tag">
                        <div class="h-8 w-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Remover tag</p>
                            <p class="text-xs text-slate-500 truncate">Remove tag do lead</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="action" data-subtype="send_whatsapp">
                        <div class="h-8 w-8 rounded-lg bg-green-100 text-green-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Enviar WhatsApp</p>
                            <p class="text-xs text-slate-500 truncate">Mensagem ao lead</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="action" data-subtype="send_webhook">
                        <div class="h-8 w-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Enviar webhook</p>
                            <p class="text-xs text-slate-500 truncate">n8n / Zapier</p>
                        </div>
                    </button>
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="action" data-subtype="assign_user">
                        <div class="h-8 w-8 rounded-lg bg-yellow-100 text-yellow-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Atribuir usuario</p>
                            <p class="text-xs text-slate-500 truncate">Responsavel pelo lead</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Timing -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Tempo</h3>
                <div class="space-y-2">
                    <button type="button" class="node-palette w-full flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-primary-300 hover:shadow-sm transition-all" data-type="delay">
                        <div class="h-8 w-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">Esperar</p>
                            <p class="text-xs text-slate-500 truncate">Delay na sequencia</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Canvas do fluxo -->
    <div class="flex-1 bg-slate-50 overflow-auto relative" id="canvas-wrapper">
        <div id="flow-canvas" class="min-h-full p-8 flex flex-col items-center relative" style="min-width: 600px;">
            <!-- SVG para conexoes -->
            <svg id="connections-layer" class="absolute inset-0 w-full h-full pointer-events-none overflow-visible" style="z-index: 1;">
                <defs>
                    <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
                        <polygon points="0 0, 10 3, 0 6" fill="#64748b" />
                    </marker>
                    <marker id="arrowhead-yes" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
                        <polygon points="0 0, 10 3, 0 6" fill="#10b981" />
                    </marker>
                    <marker id="arrowhead-no" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
                        <polygon points="0 0, 10 3, 0 6" fill="#ef4444" />
                    </marker>
                </defs>
            </svg>

            <!-- Grid de fundo -->
            <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px; z-index: 0;"></div>

            <!-- Nodes serao inseridos aqui -->
            <div id="nodes-container" class="relative z-10 flex flex-col items-center gap-6"></div>

            <!-- Placeholder quando vazio -->
            <div id="empty-canvas" class="flex flex-col items-center justify-center h-64 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <p class="text-sm font-medium">Comece adicionando um gatilho</p>
                <p class="text-xs mt-1">Clique em "Lead criado" no menu lateral</p>
            </div>
        </div>
    </div>

    <style>
        .node-card {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }
        .node-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .node-card.dragging {
            opacity: 0.8;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
        .node-card.drag-over {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }
        .drag-handle {
            cursor: grab;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .connection-line {
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            transition: stroke 0.2s, stroke-width 0.2s;
        }
        .connection-line:hover {
            stroke-width: 4;
            filter: drop-shadow(0 0 2px currentColor);
        }
        .add-node-btn {
            transition: all 0.2s;
        }
        .add-node-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .drop-indicator {
            height: 4px;
            background: #3b82f6;
            border-radius: 2px;
            margin: 8px 0;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .drop-indicator.active {
            opacity: 1;
        }
        /* Grid animado */
        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 20px 20px; }
        }
    </style>
</div>

<!-- Painel de configuracao lateral (mobile: bottom sheet, desktop: direita) -->
<div id="config-panel" class="fixed inset-y-0 right-0 w-full md:w-80 bg-white border-l border-slate-200 shadow-xl transform translate-x-full transition-transform duration-200 z-[200]">
    <div class="flex flex-col h-full">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
            <h3 id="config-title" class="font-semibold text-slate-900">Configurar</h3>
            <button type="button" id="config-close" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="config-content" class="flex-1 overflow-y-auto p-4">
            <!-- Formulario dinamico -->
        </div>
        <div class="border-t border-slate-200 p-4">
            <button type="button" id="config-save" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                Salvar configuracao
            </button>
        </div>
    </div>
</div>

<!-- Overlay para mobile -->
<div id="config-overlay" class="fixed inset-0 bg-black/50 z-[190] hidden md:hidden"></div>

<script>
window.AUTOMATION_ID = <?= (int)$automationId ?>;
</script>
