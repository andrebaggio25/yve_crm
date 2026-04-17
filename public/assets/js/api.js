/**
 * Yve CRM - API Client
 */

const API = {
    baseUrl: '',
    
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    },

    parseResponseBody(text, status) {
        const trimmed = (text || '').trim();
        if (!trimmed) {
            return { success: false, message: `Resposta vazia (HTTP ${status})` };
        }
        if (trimmed[0] === '{' || trimmed[0] === '[') {
            try {
                return JSON.parse(text);
            } catch (e) {
                return {
                    success: false,
                    message: 'Resposta JSON invalida do servidor'
                };
            }
        }
        return {
            success: false,
            message: trimmed.length > 280 ? `${trimmed.slice(0, 280)}…` : trimmed
        };
    },

    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.getCsrfToken()
            }
        };

        if (options.body && typeof options.body === 'object') {
            defaultOptions.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const mergedOptions = { ...defaultOptions, ...options };
        mergedOptions.headers = { ...defaultOptions.headers, ...options.headers };

        try {
            const response = await fetch(this.baseUrl + url, mergedOptions);
            const text = await response.text();
            const data = this.parseResponseBody(text, response.status);

            if (!response.ok) {
                const err = new Error(data.message || `HTTP ${response.status}`);
                err.data = data;
                throw err;
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    get(url) {
        return this.request(url, { method: 'GET' });
    },

    post(url, data = {}) {
        return this.request(url, { method: 'POST', body: data });
    },

    /**
     * POST multipart (nao definir Content-Type — o browser envia boundary).
     */
    async postForm(url, formData) {
        const csrf = this.getCsrfToken();
        const response = await fetch(this.baseUrl + url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf,
            },
            body: formData,
        });
        const text = await response.text();
        const data = this.parseResponseBody(text, response.status);
        if (!response.ok) {
            const err = new Error(data.message || `HTTP ${response.status}`);
            err.data = data;
            throw err;
        }
        return data;
    },

    put(url, data = {}) {
        return this.request(url, { method: 'PUT', body: data });
    },

    delete(url) {
        return this.request(url, { method: 'DELETE' });
    },

    // Leads
    leads: {
        list(params = {}) {
            const query = new URLSearchParams(params).toString();
            return API.get('/api/leads?' + query);
        },

        get(id) {
            return API.get(`/api/leads/${id}`);
        },

        create(data) {
            return API.post('/api/leads', data);
        },

        update(id, data) {
            return API.put(`/api/leads/${id}`, data);
        },

        delete(id) {
            return API.delete(`/api/leads/${id}`);
        },

        moveStage(id, stageId) {
            return API.post(`/api/leads/${id}/move-stage`, { stage_id: stageId });
        },

        addNote(id, note) {
            return API.post(`/api/leads/${id}/notes`, { note });
        },

        logFollowup(id, data) {
            return API.post(`/api/leads/${id}/followup`, data);
        },

        getEvents(id) {
            return API.get(`/api/leads/${id}/events`);
        },

        getTemplates(id) {
            return API.get(`/api/leads/${id}/templates`);
        },

        triggerWhatsApp(id, data = {}) {
            return API.post(`/api/leads/${id}/whatsapp-trigger`, data);
        },

        linkExisting(provisionalId, targetLeadId) {
            return API.post(`/api/leads/${provisionalId}/link-existing`, { target_lead_id: targetLeadId });
        },

        acceptEntry(id, data) {
            return API.post(`/api/leads/${id}/accept-entry`, data);
        },

        discardEntry(id) {
            return API.post(`/api/leads/${id}/discard-entry`, {});
        },

        importParse(file) {
            const formData = new FormData();
            formData.append('file', file);
            const csrf = API.getCsrfToken();
            if (csrf) {
                formData.append('csrf_token', csrf);
            }

            return fetch('/api/leads/import/parse', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrf,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            }).then(async (r) => {
                const text = await r.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    return {
                        success: false,
                        message: 'Resposta invalida do servidor (nao e JSON). Verifique login e CSRF.'
                    };
                }
                return data;
            });
        },

        importCommit(payload) {
            return API.post('/api/leads/import/commit', payload);
        }
    },

    // Pipelines & Kanban
    kanban: {
        getData(pipelineId, params = {}) {
            const q = new URLSearchParams(params).toString();
            const suffix = q ? `?${q}` : '';
            return API.get(`/api/pipelines/${pipelineId}/kanban${suffix}`);
        },

        listPipelines() {
            return API.get('/api/pipelines');
        }
    },

    pipelines: {
        list() {
            return API.get('/api/pipelines');
        },

        get(id) {
            return API.get(`/api/pipelines/${id}`);
        },

        create(data) {
            return API.post('/api/pipelines', data);
        },

        update(id, data) {
            return API.put(`/api/pipelines/${id}`, data);
        },

        delete(id) {
            return API.delete(`/api/pipelines/${id}`);
        }
    },

    // Tags
    tags: {
        list() {
            return API.get('/api/tags');
        },

        create(data) {
            return API.post('/api/tags', data);
        },

        update(id, data) {
            return API.put(`/api/tags/${id}`, data);
        },

        delete(id) {
            return API.delete(`/api/tags/${id}`);
        }
    },

    // Templates
    templates: {
        list() {
            return API.get('/api/templates');
        },

        create(data) {
            return API.post('/api/templates', data);
        },

        update(id, data) {
            return API.put(`/api/templates/${id}`, data);
        },

        delete(id) {
            return API.delete(`/api/templates/${id}`);
        }
    },

    // Users
    users: {
        list() {
            return API.get('/api/users');
        },

        create(data) {
            return API.post('/api/users', data);
        },

        update(id, data) {
            return API.put(`/api/users/${id}`, data);
        },

        delete(id) {
            return API.delete(`/api/users/${id}`);
        }
    },

    // Dashboard
    dashboard: {
        metrics(params = {}) {
            const query = new URLSearchParams(params).toString();
            return API.get('/api/dashboard/metrics?' + query);
        },
        teamUsers() {
            return API.get('/api/dashboard/team-users');
        }
    },

    // Migrations (admin)
    migrations: {
        status() {
            return API.get('/api/migrations/status');
        },

        run() {
            return API.post('/api/migrations/run');
        },

        rollback() {
            return API.post('/api/migrations/rollback');
        },

        seed() {
            return API.post('/api/migrations/seed');
        },

        reset() {
            return API.post('/api/migrations/reset');
        }
    }
};

// Expor globalmente
window.API = API;
