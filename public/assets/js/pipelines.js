/**
 * Yve CRM - Pipelines JavaScript
 */
const PipelinesI18n = window.PIPELINES_I18N || {};

const Pipelines = {
    modal: null,
    form: null,
    isEditing: false,
    currentId: null,
    stagesModal: null,
    stagesPipelineId: null,
    stages: [],

    init() {
        this.modal = document.getElementById('pipeline-modal');
        this.form = document.getElementById('pipeline-form');
        this.stagesModal = document.getElementById('stages-modal');
        this.bindEvents();
    },

    bindEvents() {
        document.getElementById('btn-add-pipeline')?.addEventListener('click', () => this.openModal());
        document.getElementById('modal-close')?.addEventListener('click', () => this.closeModal());
        document.getElementById('btn-cancel')?.addEventListener('click', () => this.closeModal());
        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) this.closeModal();
        });
        this.form?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.save();
        });
        document.getElementById('stages-modal-close')?.addEventListener('click', () => this.closeStagesModal());
        this.stagesModal?.addEventListener('click', (e) => {
            if (e.target === this.stagesModal) this.closeStagesModal();
        });
        document.getElementById('btn-save-stages')?.addEventListener('click', () => this.saveStages());
        document.getElementById('btn-add-stage')?.addEventListener('click', () => this.addStageRemote());
    },

    openModal(pipeline = null) {
        this.isEditing = !!pipeline;
        this.currentId = pipeline?.id || null;
        document.getElementById('modal-title').textContent = this.isEditing ? 'Editar Pipeline' : 'Novo Pipeline';
        if (this.isEditing) {
            document.getElementById('pipeline-id').value = pipeline.id;
            document.getElementById('pipeline-name').value = pipeline.name || '';
            document.getElementById('pipeline-description').value = pipeline.description || '';
            document.getElementById('pipeline-status').value = pipeline.is_active ? '1' : '0';
        } else {
            this.form.reset();
            document.getElementById('pipeline-id').value = '';
            document.getElementById('pipeline-status').value = '1';
        }
        this.modal.classList.remove('hidden');
        this.modal.classList.add('flex');
        document.getElementById('pipeline-name')?.focus();
    },

    closeModal() {
        this.modal?.classList.add('hidden');
        this.modal?.classList.remove('flex');
        this.isEditing = false;
        this.currentId = null;
    },

    async save() {
        const data = Object.fromEntries(new FormData(this.form).entries());
        data.is_active = parseInt(data.is_active, 10);
        const btnSave = document.getElementById('btn-save');
        const originalText = btnSave.textContent;
        btnSave.disabled = true;
        btnSave.textContent = PipelinesI18n.saving || '...';
        try {
            const response = this.isEditing
                ? await API.put(`/api/pipelines/${this.currentId}`, data)
                : await API.post('/api/pipelines', data);
            if (response.success) {
                App.toast('OK', 'success');
                this.closeModal();
                location.reload();
            } else {
                App.toast(response.message || 'Erro', 'error');
            }
        } catch (error) {
            App.toast('Erro ao salvar pipeline', 'error');
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = originalText;
        }
    },

    async edit(id) {
        try {
            const response = await API.get(`/api/pipelines/${id}`);
            if (response.success && response.data.pipeline) {
                this.openModal(response.data.pipeline);
            }
        } catch (error) {
            App.toast('Erro', 'error');
        }
    },

    async delete(id, name) {
        const ok = await App.confirmDialog({
            title: 'Excluir',
            message: `Excluir "${name}"?`,
            confirmText: 'Excluir',
            danger: true
        });
        if (!ok) return;
        try {
            const response = await API.delete(`/api/pipelines/${id}`);
            if (response.success) {
                location.reload();
            } else {
                App.toast(response.message || 'Erro', 'error');
            }
        } catch (error) {
            App.toast('Erro', 'error');
        }
    },

    async editStages(id) {
        this.stagesPipelineId = id;
        const r = await API.get(`/api/pipelines/${id}`);
        if (!r.success || !r.data.pipeline) {
            App.toast('Erro', 'error');
            return;
        }
        this.stages = (r.data.pipeline.stages || []).map((s) => ({
            id: s.id,
            name: s.name,
            color_token: s.color_token || '#6B7280',
            stage_type: s.stage_type || 'intermediate',
            win_probability: parseFloat(s.win_probability) || 0
        }));
        this.renderStagesList();
        this.stagesModal.classList.remove('hidden');
        this.stagesModal.classList.add('flex');
    },

    closeStagesModal() {
        this.stagesModal?.classList.add('hidden');
        this.stagesModal?.classList.remove('flex');
        this.stagesPipelineId = null;
    },

    renderStagesList() {
        const el = document.getElementById('stages-list');
        if (!el) return;
        const types = ['initial', 'intermediate', 'hot', 'warm', 'cold', 'won', 'lost'];
        el.innerHTML = this.stages.map((s, idx) => {
            const typeOpts = types.map(t =>
                `<option value="${t}" ${s.stage_type === t ? 'selected' : ''}>${t}</option>`
            ).join('');
            const safeName = String(s.name || '').replace(/</g, '&lt;');
            return `
            <div class="rounded-lg border border-slate-200 p-3" data-idx="${idx}">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">${PipelinesI18n.stageName || 'Nome'}</label>
                        <input type="text" class="stage-name w-full rounded border border-slate-200 px-2 py-1 text-sm" value="${safeName}">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">${PipelinesI18n.color || 'Cor'}</label>
                        <input type="text" class="stage-color w-full rounded border border-slate-200 px-2 py-1 text-sm" value="${(s.color_token || '').replace(/"/g, '&quot;')}">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">${PipelinesI18n.type || 'Tipo'}</label>
                        <select class="stage-type w-full rounded border border-slate-200 px-2 py-1 text-sm">${typeOpts}</select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="btn-remove-stage rounded border border-red-200 px-2 py-1 text-xs text-red-700" data-idx="${idx}">${PipelinesI18n.delete || 'Excluir'}</button>
                    </div>
                </div>
            </div>`;
        }).join('');

        el.querySelectorAll('.btn-remove-stage').forEach((btn) => {
            btn.addEventListener('click', () => this.removeStage(parseInt(btn.getAttribute('data-idx'), 10)));
        });
    },

    async addStageRemote() {
        if (!this.stagesPipelineId) return;
        const name = window.prompt('Nome da etapa', 'Nova etapa') || 'Nova etapa';
        try {
            const r = await API.post(`/api/pipelines/${this.stagesPipelineId}/stages`, {
                name: name.trim(),
                stage_type: 'intermediate',
                color_token: '#6B7280'
            });
            if (r.success) {
                await this.editStages(this.stagesPipelineId);
            } else {
                App.toast(r.message || 'Erro', 'error');
            }
        } catch (e) {
            App.toast('Erro', 'error');
        }
    },

    async removeStage(idx) {
        const s = this.stages[idx];
        if (!s || !this.stagesPipelineId) return;
        const move = window.prompt('ID da etapa de destino para os leads (deixe vazio se nao houver leads nesta etapa):', '');
        const moveId = move && move.trim() !== '' ? parseInt(move.trim(), 10) : 0;
        const body = moveId > 0 ? { move_to_stage_id: moveId } : {};
        if (!window.confirm('Excluir etapa?')) return;
        try {
            const r = await API.deleteWithBody(`/api/pipelines/${this.stagesPipelineId}/stages/${s.id}`, body);
            if (r.success) {
                await this.editStages(this.stagesPipelineId);
            } else {
                App.toast(r.message || PipelinesI18n.cannotDelete || 'Erro', 'error');
            }
        } catch (e) {
            App.toast(e.message || PipelinesI18n.cannotDelete, 'error');
        }
    },

    async saveStages() {
        if (!this.stagesPipelineId) return;
        const el = document.getElementById('stages-list');
        const rows = el?.querySelectorAll('[data-idx]') || [];
        const payload = [];
        rows.forEach((row) => {
            const idx = parseInt(row.getAttribute('data-idx'), 10);
            const s = this.stages[idx];
            if (!s) return;
            payload.push({
                id: s.id,
                name: row.querySelector('.stage-name')?.value || s.name,
                color_token: row.querySelector('.stage-color')?.value || s.color_token,
                stage_type: row.querySelector('.stage-type')?.value || s.stage_type,
                win_probability: s.win_probability
            });
        });
        try {
            const r = await API.put(`/api/pipelines/${this.stagesPipelineId}/stages`, { stages: payload });
            if (r.success) {
                App.toast('OK', 'success');
                this.closeStagesModal();
                location.reload();
            } else {
                App.toast(r.message || 'Erro', 'error');
            }
        } catch (e) {
            App.toast('Erro', 'error');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.pipelines-page')) {
        Pipelines.init();
    }
});

window.Pipelines = Pipelines;
