<?php
$title = 'Importar';
$pageTitle = 'Importar Leads';
?>
<div class="mx-auto max-w-5xl space-y-6" id="import-root">
    <div
        class="cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 bg-white p-8 text-center transition hover:border-primary-400 hover:bg-primary-50/30 sm:p-12"
        id="upload-area"
    >
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-500">
            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900">Arraste CSV, XLS ou XLSX</h3>
        <p class="mt-2 text-slate-600">ou <span class="font-medium text-primary-600">clique para selecionar</span></p>
        <input type="file" id="file-input" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel" class="hidden">
        <div class="mt-6 border-t border-slate-200 pt-6 text-left text-xs text-slate-500">
            <p>Primeira linha deve ser o cabecalho. O campo <strong>nome</strong> e obrigatorio (coluna ou valor padrao).</p>
            <p class="mt-1">Produtos podem ser separados por virgula, ponto-e-virgula ou | — cada item vira uma <strong>tag</strong> no lead.</p>
        </div>
    </div>

    <div id="map-section" class="hidden space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-slate-900">Mapeamento de colunas</h2>
            <p class="mt-1 text-sm text-slate-600">Ligue cada campo do CRM a uma coluna da planilha. Use o valor padrao quando a celula estiver vazia.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2" id="mapping-fields"></div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-slate-900">Amostra e ajustes</h2>
            <p class="mt-1 text-sm text-slate-600">Edite celulas abaixo para corrigir linhas especificas antes de importar (aplica-se a todas as linhas do arquivo apenas onde voce alterar).</p>
            <div class="mt-4 overflow-x-auto" id="preview-wrap"></div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
            <button type="button" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50" id="btn-restart">
                Outro arquivo
            </button>
            <button type="button" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" id="btn-commit" disabled>
                Importar leads
            </button>
        </div>
    </div>

    <div id="result-area" class="hidden">
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm" id="result-card"></div>
    </div>
</div>

<?php $scripts = ['imports']; ?>
