/**
 * Yve CRM - Pipelines JavaScript
 */

const Pipelines = {
    modal: null,
    form: null,
    isEditing: false,
    currentId: null,

    init() {
        this.modal = document.getElementById('pipeline-modal');
        this.form = document.getElementById('pipeline-form');

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

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && !this.modal.classList.contains('hidden')) {
                this.closeModal();
            }
        });
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
        const formData = new FormData(this.form);
        const data = Object.fromEntries(formData.entries());

        data.is_active = parseInt(data.is_active);

        const btnSave = document.getElementById('btn-save');
        const originalText = btnSave.textContent;
        btnSave.disabled = true;
        btnSave.textContent = 'Salvando...';

        try {
            let response;
            
            if (this.isEditing) {
                response = await API.put(`/api/pipelines/${this.currentId}`, data);
            } else {
                response = await API.post('/api/pipelines', data);
            }

            if (response.success) {
                App.toast(
                    this.isEditing ? 'Pipeline atualizado com sucesso' : 'Pipeline criado com sucesso',
                    'success'
                );
                this.closeModal();
                location.reload();
            } else {
                App.toast(response.message || 'Erro ao salvar pipeline', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
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
            } else {
                App.toast('Erro ao carregar dados do pipeline', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            App.toast('Erro ao carregar dados do pipeline', 'error');
        }
    },

    async delete(id, name) {
        const ok = await App.confirmDialog({
            title: 'Excluir pipeline',
            message: `Tem certeza que deseja excluir o pipeline "${name}"?\n\nEsta acao nao pode ser desfeita.`,
            confirmText: 'Excluir',
            danger: true
        });
        if (!ok) return;

        try {
            const response = await API.delete(`/api/pipelines/${id}`);

            if (response.success) {
                App.toast('Pipeline excluido com sucesso', 'success');
                location.reload();
            } else {
                App.toast(response.message || 'Erro ao excluir pipeline', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            App.toast('Erro ao excluir pipeline', 'error');
        }
    },

    editStages(id) {
        App.toast('Edicao de etapas em desenvolvimento', 'info');
    }
};

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.pipelines-page')) {
        Pipelines.init();
    }
});

window.Pipelines = Pipelines;
