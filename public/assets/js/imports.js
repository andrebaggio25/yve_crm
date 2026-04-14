(function () {
    const FIELD_DEFS = [
        { key: 'nome', label: 'Nome', required: true },
        { key: 'telefone', label: 'Telefone', required: false },
        { key: 'email', label: 'Email', required: false },
        { key: 'origem', label: 'Origem', required: false },
        { key: 'produto', label: 'Produto / tags', required: false },
        { key: 'valor', label: 'Valor', required: false }
    ];

    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const mapSection = document.getElementById('map-section');
    const mappingFields = document.getElementById('mapping-fields');
    const previewWrap = document.getElementById('preview-wrap');
    const resultArea = document.getElementById('result-area');
    const resultCard = document.getElementById('result-card');
    const btnRestart = document.getElementById('btn-restart');
    const btnCommit = document.getElementById('btn-commit');

    if (!uploadArea || !fileInput) return;

    const defaultUploadHtml = uploadArea.innerHTML;

    let state = {
        token: null,
        headers: [],
        previewRows: [],
        totalRows: 0,
        suggested: {},
        fields: {},
        overrides: {}
    };

    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('border-primary-500', 'bg-primary-50/50');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('border-primary-500', 'bg-primary-50/50');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('border-primary-500', 'bg-primary-50/50');
        const files = e.dataTransfer.files;
        if (files.length) handleFile(files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) handleFile(e.target.files[0]);
    });

    btnRestart?.addEventListener('click', () => resetWizard());
    btnCommit?.addEventListener('click', () => runCommit());

    function allowedFile(name) {
        const n = name.toLowerCase();
        return n.endsWith('.csv') || n.endsWith('.xls') || n.endsWith('.xlsx');
    }

    async function handleFile(file) {
        if (!allowedFile(file.name)) {
            App.toast('Use arquivo CSV, XLS ou XLSX', 'warning');
            return;
        }

        uploadArea.innerHTML =
            '<div class="flex flex-col items-center gap-3 py-8"><div class="spinner h-10 w-10 rounded-full border-2 border-slate-200 border-t-primary-600"></div><p class="text-sm text-slate-600">Analisando arquivo...</p></div>';

        try {
            const result = await API.leads.importParse(file);
            if (!result.success || !result.data) {
                App.toast(result.message || 'Erro ao ler arquivo', 'error');
                uploadArea.innerHTML = defaultUploadHtml;
                return;
            }

            const d = result.data;
            state.token = d.token;
            state.headers = d.headers || [];
            state.previewRows = d.preview_rows || [];
            state.totalRows = d.total_rows || 0;
            state.suggested = d.suggested_mapping || {};
            state.overrides = {};

            initFieldsFromSuggested();
            renderMappingForm();
            renderPreview();
            uploadArea.classList.add('hidden');
            mapSection.classList.remove('hidden');
            resultArea.classList.add('hidden');
            btnCommit.disabled = false;
        } catch (error) {
            console.error(error);
            App.toast('Erro ao importar arquivo', 'error');
            uploadArea.innerHTML = defaultUploadHtml;
        }
    }

    function initFieldsFromSuggested() {
        state.fields = {};
        FIELD_DEFS.forEach((fd) => {
            const col = state.suggested[fd.key];
            state.fields[fd.key] = {
                col: col !== null && col !== undefined ? col : '',
                default: ''
            };
        });
    }

    function readFieldsFromDom() {
        const out = {};
        FIELD_DEFS.forEach((fd) => {
            const sel = document.getElementById(`map-col-${fd.key}`);
            const def = document.getElementById(`map-def-${fd.key}`);
            const v = sel?.value;
            out[fd.key] = {
                col: v === '' || v === undefined ? null : parseInt(v, 10),
                default: def?.value?.trim() ?? ''
            };
        });
        return out;
    }

    function onMappingChanged() {
        state.fields = readFieldsFromDom();
        renderPreview();
    }

    function renderMappingForm() {
        const opts = ['<option value="">— Nenhuma —</option>']
            .concat(
                state.headers.map((h, i) => {
                    const lab = h || `Coluna ${i + 1}`;
                    return `<option value="${i}">${i + 1}. ${escapeHtml(String(lab))}</option>`;
                })
            )
            .join('');

        mappingFields.innerHTML = FIELD_DEFS.map((fd) => {
            const f = state.fields[fd.key];
            const selVal = f.col === '' || f.col === null ? '' : String(f.col);
            const req = fd.required ? ' <span class="text-red-500">*</span>' : '';
            return `
            <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-3">
                <label class="block text-sm font-medium text-slate-800" for="map-col-${fd.key}">${escapeHtml(fd.label)}${req}</label>
                <select id="map-col-${fd.key}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">${opts}</select>
                <label class="mt-2 block text-xs text-slate-500" for="map-def-${fd.key}">Valor padrao (se celula vazia)</label>
                <input type="text" id="map-def-${fd.key}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="Opcional" value="${escapeAttr(f.default || '')}">
            </div>`;
        }).join('');

        FIELD_DEFS.forEach((fd) => {
            const f = state.fields[fd.key];
            const sel = document.getElementById(`map-col-${fd.key}`);
            if (sel && f.col !== '' && f.col !== null) sel.value = String(f.col);
        });

        FIELD_DEFS.forEach((fd) => {
            const sel = document.getElementById(`map-col-${fd.key}`);
            const def = document.getElementById(`map-def-${fd.key}`);
            if (sel) sel.addEventListener('change', () => onMappingChanged());
            if (def) def.addEventListener('input', () => onMappingChanged());
        });
    }

    function resolveField(key, row, fields, overrides, rowIdx) {
        const o = overrides[rowIdx];
        if (o && o[key] !== undefined && String(o[key]).trim() !== '') {
            return String(o[key]).trim();
        }
        const cfg = fields[key];
        let raw = '';
        if (cfg.col !== null && cfg.col !== '') {
            raw = String(row[cfg.col] ?? '').trim();
        }
        if (raw === '' && cfg.default) {
            return cfg.default.trim();
        }
        return raw;
    }

    function renderPreview() {
        const fields = readFieldsFromDom();
        const rows = state.previewRows;

        let head = `<thead><tr class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
            <th class="sticky left-0 z-10 bg-slate-50 px-2 py-2">#</th>`;
        FIELD_DEFS.forEach((fd) => {
            head += `<th class="min-w-[140px] px-2 py-2">${escapeHtml(fd.label)}</th>`;
        });
        head += '</tr></thead>';

        let body = '<tbody class="divide-y divide-slate-100">';
        rows.forEach((row, idx) => {
            body += `<tr class="text-sm"><td class="sticky left-0 bg-white px-2 py-2 font-mono text-xs text-slate-500">${idx + 1}</td>`;
            FIELD_DEFS.forEach((fd) => {
                const val = resolveField(fd.key, row, fields, state.overrides, idx);
                const inputVal =
                    state.overrides[idx] && state.overrides[idx][fd.key] !== undefined
                        ? state.overrides[idx][fd.key]
                        : val;
                body += `<td class="px-1 py-1"><input type="text" class="w-full min-w-[120px] rounded border border-slate-200 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500/30" 
                    data-row="${idx}" data-field="${fd.key}" value="${escapeAttr(inputVal)}" /></td>`;
            });
            body += '</tr>';
        });
        body += '</tbody>';

        previewWrap.innerHTML = `<table class="min-w-full border border-slate-200 text-sm">${head}${body}</table>
            <p class="mt-2 text-xs text-slate-500">Mostrando ${rows.length} de ${state.totalRows} linhas. A importacao usa o arquivo completo.</p>`;

        previewWrap.querySelectorAll('input[data-row]').forEach((inp) => {
            inp.addEventListener('change', () => {
                const r = parseInt(inp.getAttribute('data-row'), 10);
                const f = inp.getAttribute('data-field');
                if (!state.overrides[r]) state.overrides[r] = {};
                state.overrides[r][f] = inp.value;
            });
        });
    }

    async function runCommit() {
        const fields = readFieldsFromDom();
        if (fields.nome.col === null && !fields.nome.default) {
            App.toast('Defina a coluna ou valor padrao para Nome', 'warning');
            return;
        }

        btnCommit.disabled = true;
        btnCommit.textContent = 'Importando...';

        const payload = {
            token: state.token,
            fields,
            overrides: state.overrides
        };

        try {
            const result = await API.leads.importCommit(payload);
            if (!result.success) {
                App.toast(result.message || 'Erro na importacao', 'error');
                return;
            }
            mapSection.classList.add('hidden');
            resultArea.classList.remove('hidden');
            showResult(result);
        } catch (error) {
            console.error(error);
            App.toast(error.message || 'Erro na importacao', 'error');
        } finally {
            btnCommit.disabled = false;
            btnCommit.textContent = 'Importar leads';
        }
    }

    function resetWizard() {
        state = {
            token: null,
            headers: [],
            previewRows: [],
            totalRows: 0,
            suggested: {},
            fields: {},
            overrides: {}
        };
        uploadArea.innerHTML = defaultUploadHtml;
        uploadArea.classList.remove('hidden');
        mapSection.classList.add('hidden');
        resultArea.classList.add('hidden');
        fileInput.value = '';
    }

    function showResult(result) {
        const isSuccess = result.success && result.data && result.data.imported > 0;
        const total = result.data ? result.data.total || 0 : 0;
        const imported = result.data ? result.data.imported || 0 : 0;
        const duplicates = result.data ? result.data.duplicates || 0 : 0;
        const errors = result.data && result.data.errors ? result.data.errors : [];

        let errorsHtml = '';
        if (errors.length > 0) {
            errorsHtml =
                '<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-left text-sm text-amber-900"><p class="font-semibold">Avisos</p>';
            errors.slice(0, 8).forEach((e) => {
                errorsHtml += '<p class="mt-1">' + escapeHtml(String(e)) + '</p>';
            });
            errorsHtml += '</div>';
        }

        resultCard.innerHTML = `
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full ${isSuccess ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'}">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${isSuccess
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'}
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900">${isSuccess ? 'Importacao concluida!' : result.message || 'Erro na importacao'}</h3>
            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-4">
                    <div class="text-2xl font-bold text-slate-900">${total}</div>
                    <div class="text-xs text-slate-500">Linhas processadas</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <div class="text-2xl font-bold text-emerald-600">${imported}</div>
                    <div class="text-xs text-slate-500">Importados</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <div class="text-2xl font-bold text-amber-600">${duplicates}</div>
                    <div class="text-xs text-slate-500">Duplicados (telefone)</div>
                </div>
            </div>
            ${errorsHtml}
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" onclick="location.reload()">Nova importacao</button>
                <a href="/kanban" class="inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Ver Kanban</a>
            </div>
        `;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
})();
