/**
 * Yve CRM - Users JavaScript
 */

const Users = {
    modal: null,
    form: null,
    isEditing: false,
    currentId: null,

    init() {
        this.modal = document.getElementById('user-modal');
        this.form = document.getElementById('user-form');

        this.bindEvents();
    },

    bindEvents() {
        document.getElementById('btn-add-user')?.addEventListener('click', () => this.openModal());
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

    openModal(user = null) {
        this.isEditing = !!user;
        this.currentId = user?.id || null;

        document.getElementById('modal-title').textContent = this.isEditing ? 'Editar Usuario' : 'Novo Usuario';
        
        const passwordLabel = document.getElementById('password-label');
        const passwordHint = document.getElementById('password-hint');
        const passwordInput = document.getElementById('user-password');

        if (this.isEditing) {
            passwordLabel.textContent = 'Nova Senha (opcional)';
            passwordHint.classList.remove('hidden');
            passwordInput.required = false;
            
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-name').value = user.name || '';
            document.getElementById('user-email').value = user.email || '';
            document.getElementById('user-phone').value = user.phone || '';
            document.getElementById('user-role').value = user.role || '';
            document.getElementById('user-status').value = user.status || 'active';
            if (document.getElementById('user-locale')) {
                document.getElementById('user-locale').value = user.locale || 'es';
            }
            passwordInput.value = '';
        } else {
            passwordLabel.textContent = 'Senha';
            passwordHint.classList.add('hidden');
            passwordInput.required = true;
            
            this.form.reset();
            document.getElementById('user-id').value = '';
            document.getElementById('user-status').value = 'active';
            if (document.getElementById('user-locale')) {
                document.getElementById('user-locale').value = 'es';
            }
        }

        this.modal.classList.remove('hidden');
        this.modal.classList.add('flex');
        document.getElementById('user-name')?.focus();
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

        if (!data.password && this.isEditing) {
            delete data.password;
        }

        const btnSave = document.getElementById('btn-save');
        const originalText = btnSave.textContent;
        btnSave.disabled = true;
        btnSave.textContent = 'Salvando...';

        try {
            let response;
            
            if (this.isEditing) {
                response = await API.users.update(this.currentId, data);
            } else {
                response = await API.users.create(data);
            }

            if (response.success) {
                App.toast(
                    this.isEditing ? 'Usuario atualizado com sucesso' : 'Usuario criado com sucesso',
                    'success'
                );
                this.closeModal();
                this.refreshTable();
            } else {
                App.toast(response.message || 'Erro ao salvar usuario', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            App.toast('Erro ao salvar usuario', 'error');
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = originalText;
        }
    },

    async edit(id) {
        try {
            const response = await API.get(`/api/users/${id}`);
            
            if (response.success && response.data.user) {
                this.openModal(response.data.user);
            } else {
                App.toast('Erro ao carregar dados do usuario', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            
            const row = document.querySelector(`tr[data-user-id="${id}"]`);
            if (row) {
                const user = {
                    id: id,
                    name: row.cells[0].textContent,
                    email: row.cells[1].textContent,
                    phone: row.cells[2].textContent !== '-' ? row.cells[2].textContent : '',
                    role: this.getRoleFromBadge(row.cells[3].textContent),
                    status: row.cells[4].textContent.trim().toLowerCase() === 'ativo' ? 'active' : 'inactive'
                };
                this.openModal(user);
            }
        }
    },

    getRoleFromBadge(text) {
        const map = {
            'Administrador': 'admin',
            'Gestor': 'gestor',
            'Vendedor': 'vendedor'
        };
        return map[text.trim()] || 'vendedor';
    },

    async delete(id, name) {
        const ok = await App.confirmDialog({
            title: 'Excluir usuario',
            message: `Tem certeza que deseja excluir o usuario "${name}"?\n\nEsta acao nao pode ser desfeita.`,
            confirmText: 'Excluir',
            danger: true
        });
        if (!ok) return;

        try {
            const response = await API.users.delete(id);

            if (response.success) {
                App.toast('Usuario excluido com sucesso', 'success');
                this.refreshTable();
            } else {
                App.toast(response.message || 'Erro ao excluir usuario', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            App.toast('Erro ao excluir usuario', 'error');
        }
    },

    async refreshTable() {
        try {
            const response = await API.users.list();
            
            if (response.success) {
                location.reload();
            }
        } catch (error) {
            console.error('Erro ao atualizar tabela:', error);
            location.reload();
        }
    }
};

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.users-page')) {
        Users.init();
    }
});

window.Users = Users;
