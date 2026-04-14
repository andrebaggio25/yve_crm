/**
 * Yve CRM - Templates JavaScript
 */

const Templates = {
    modal: null,
    form: null,
    isEditing: false,
    currentId: null,
    pipelines: [],
    stages: [],

    init() {
        console.log('[Templates] Inicializando...');
        this.modal = document.getElementById('template-modal');
        this.form = document.getElementById('template-form');
        
        if (!this.modal || !this.form) {
            console.error('[Templates] Modal ou form nao encontrados!');
            return;
        }
        
        this.bindEvents();
        this.loadPipelines();
        console.log('[Templates] Inicializado com sucesso');
    },
    
    async loadPipelines() {
        try {
            // Verifica se já carregou
            if (this.pipelines.length > 0) {
                return;
            }
            
            const res = await API.pipelines.list();
            if (res.success && res.data.pipelines) {
                this.pipelines = res.data.pipelines;
                const select = document.getElementById('template-pipeline');
                if (select) {
                    select.innerHTML = '<option value="">-- Nenhuma (global) --</option>';
                    this.pipelines.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name;
                        select.appendChild(opt);
                    });
                }
            } else {
                console.error('Erro ao carregar pipelines:', res);
            }
        } catch (e) {
            console.error('Erro ao carregar pipelines:', e);
            App.toast('Erro ao carregar pipelines', 'error');
        }
    },
    
    loadStagesForPipeline(pipelineId) {
        const stageSelect = document.getElementById('template-stage');
        if (!stageSelect) return;
        
        stageSelect.innerHTML = '<option value="">-- Todas as etapas --</option>';
        
        if (!pipelineId) {
            stageSelect.disabled = true;
            return;
        }
        
        const pipeline = this.pipelines.find(p => String(p.id) === String(pipelineId));
        if (pipeline && pipeline.stages && pipeline.stages.length > 0) {
            pipeline.stages.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                stageSelect.appendChild(opt);
            });
            stageSelect.disabled = false;
        } else {
            // Se nao tem stages carregados, desabilita
            stageSelect.disabled = true;
        }
    },

    bindEvents() {
        document.getElementById('btn-add-template')?.addEventListener('click', () => this.openModal());
        document.getElementById('template-modal-close')?.addEventListener('click', () => this.closeModal());
        document.getElementById('template-btn-cancel')?.addEventListener('click', () => this.closeModal());

        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) this.closeModal();
        });

        // Delegacao de eventos para botões da tabela (funciona mesmo apos reload)
        const tableBody = document.getElementById('templates-table-body');
        if (tableBody) {
            tableBody.addEventListener('click', (e) => {
                console.log('[Templates] Clique na tabela:', e.target);
                const editBtn = e.target.closest('.btn-edit-template');
                const deleteBtn = e.target.closest('.btn-delete-template');
                
                if (editBtn) {
                    const id = editBtn.getAttribute('data-id');
                    console.log('[Templates] Botao editar clicado, ID:', id);
                    if (id) this.edit(parseInt(id, 10));
                    return;
                }
                
                if (deleteBtn) {
                    const id = deleteBtn.getAttribute('data-id');
                    const name = deleteBtn.getAttribute('data-name') || 'este template';
                    console.log('[Templates] Botao excluir clicado, ID:', id);
                    if (id) this.deleteFromList(parseInt(id, 10), name);
                    return;
                }
            });
        } else {
            console.warn('[Templates] Table body nao encontrado');
        }

        document.getElementById('template-btn-delete')?.addEventListener('click', () => this.deleteCurrent());

        this.form?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.save();
        });

        // Cascading pipeline > etapa
        document.getElementById('template-pipeline')?.addEventListener('change', (e) => {
            this.loadStagesForPipeline(e.target.value);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && !this.modal.classList.contains('hidden')) {
                this.closeModal();
            }
        });
    },

    openModal(template = null) {
        this.isEditing = !!template;
        this.currentId = template?.id || null;

        document.getElementById('template-modal-title').textContent = this.isEditing ? 'Editar Template' : 'Novo Template';
        document.getElementById('template-btn-delete').hidden = !this.isEditing;

        if (this.isEditing) {
            document.getElementById('template-id').value = template.id;
            document.getElementById('template-name').value = template.name || '';
            document.getElementById('template-channel').value = template.channel || 'whatsapp';
            document.getElementById('template-stage-type').value = template.stage_type || 'any';
            document.getElementById('template-content').value = template.content || '';
            document.getElementById('template-active').checked = !!template.is_active;
            document.getElementById('template-position').value = template.position || 1;
            
            // Pipeline e etapa
            const pipelineSelect = document.getElementById('template-pipeline');
            const stageSelect = document.getElementById('template-stage');
            
            if (pipelineSelect) {
                pipelineSelect.value = template.pipeline_id || '';
            }
            
            // Carrega stages (se houver pipeline_id)
            this.loadStagesForPipeline(template.pipeline_id || null);
            
            // Seta o stage_id apos carregar
            if (stageSelect) {
                if (template.stage_id && template.pipeline_id) {
                    stageSelect.value = template.stage_id;
                } else {
                    stageSelect.value = '';
                }
            }
        } else {
            this.form.reset();
            document.getElementById('template-id').value = '';
            document.getElementById('template-active').checked = true;
            document.getElementById('template-position').value = 1;
            document.getElementById('template-pipeline').value = '';
            document.getElementById('template-stage').value = '';
            document.getElementById('template-stage').disabled = true;
        }

        this.modal.classList.remove('hidden');
        this.modal.classList.add('flex');
        document.getElementById('template-name')?.focus();
    },

    closeModal() {
        this.modal?.classList.add('hidden');
        this.modal?.classList.remove('flex');
        this.isEditing = false;
        this.currentId = null;
    },

    async save() {
        const name = document.getElementById('template-name').value.trim();
        const content = document.getElementById('template-content').value;
        const channel = document.getElementById('template-channel').value;
        const stage_type = document.getElementById('template-stage-type').value;
        const is_active = document.getElementById('template-active').checked;
        const position = parseInt(document.getElementById('template-position').value, 10) || 1;
        const pipeline_id = document.getElementById('template-pipeline').value || null;
        const stage_id = document.getElementById('template-stage').value || null;

        const payload = { 
            name, 
            content, 
            channel, 
            stage_type, 
            is_active,
            position,
            pipeline_id,
            stage_id
        };
        const btn = document.getElementById('template-btn-save');
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            let res;
            if (this.isEditing) {
                res = await API.templates.update(this.currentId, payload);
            } else {
                res = await API.templates.create(payload);
            }

            if (res.success) {
                App.toast(this.isEditing ? 'Template atualizado' : 'Template criado', 'success');
                this.closeModal();
                location.reload();
            } else {
                App.toast(res.message || 'Erro ao salvar', 'error');
            }
        } catch (e) {
            console.error(e);
            App.toast('Erro ao salvar template', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    },

    async edit(id) {
        try {
            console.log('[Templates] Editando template ID:', id);
            
            // Recarrega pipelines primeiro para garantir o cascading
            await this.loadPipelines();
            console.log('[Templates] Pipelines carregados:', this.pipelines.length);
            
            const list = await API.templates.list();
            console.log('[Templates] Lista de templates:', list);
            
            if (!list.success || !list.data || !list.data.templates) {
                App.toast('Erro ao carregar templates', 'error');
                console.error('API response invalida:', list);
                return;
            }
            
            const t = list.data.templates.find((x) => String(x.id) === String(id));
            console.log('[Templates] Template encontrado:', t);
            
            if (t) {
                this.openModal(t);
            } else {
                App.toast('Template nao encontrado na lista', 'error');
                console.error('Template ID nao encontrado:', id, 'IDs disponiveis:', list.data.templates.map(x => x.id));
            }
        } catch (e) {
            console.error('Erro ao editar template:', e);
            App.toast('Erro ao carregar template: ' + (e.message || 'Erro desconhecido'), 'error');
        }
    },

    async deleteCurrent() {
        if (!this.currentId) return;
        const ok = await App.confirmDialog({
            title: 'Excluir template',
            message: 'Tem certeza? Esta acao nao pode ser desfeita.',
            confirmText: 'Excluir',
            danger: true
        });
        if (!ok) return;

        try {
            const res = await API.templates.delete(this.currentId);
            if (res.success) {
                App.toast('Template excluido', 'success');
                this.closeModal();
                location.reload();
            } else {
                App.toast(res.message || 'Erro ao excluir', 'error');
            }
        } catch (e) {
            console.error(e);
            App.toast('Erro ao excluir', 'error');
        }
    },

    async deleteFromList(id, name) {
        const ok = await App.confirmDialog({
            title: 'Excluir template',
            message: `Tem certeza que deseja excluir "${name}"? Esta acao nao pode ser desfeita.`,
            confirmText: 'Excluir',
            danger: true
        });
        if (!ok) return;

        try {
            const res = await API.templates.delete(id);
            if (res.success) {
                App.toast('Template excluido', 'success');
                // Remove a linha da tabela sem recarregar
                const row = document.querySelector(`tr[data-template-id="${id}"]`);
                if (row) row.remove();
            } else {
                App.toast(res.message || 'Erro ao excluir', 'error');
            }
        } catch (e) {
            console.error(e);
            App.toast('Erro ao excluir', 'error');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.templates-page')) {
        Templates.init();
    }
});

window.Templates = Templates;
