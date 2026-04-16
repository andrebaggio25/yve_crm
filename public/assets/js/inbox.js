/**
 * Inbox WhatsApp — lista, chat e polling.
 */
const Inbox = {
    state: {
        conversations: [],
        activeId: null,
        pollList: null,
        pollMsg: null,
    },

    init() {
        if (!document.querySelector('.inbox-page')) return;

        document.getElementById('inbox-filter')?.addEventListener('change', () => this.loadList());
        document.getElementById('inbox-composer')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.send();
        });

        (async () => {
            await this.loadList();
            await this.openFromHash();
        })();
        this.state.pollList = setInterval(() => this.loadList(), 10000);
        window.addEventListener('hashchange', () => this.openFromHash());
    },

    /** Abre conversa quando a URL tem #conv-{id} (ex.: vindo do Kanban). */
    async openFromHash() {
        const h = (location.hash || '').replace(/^#/, '');
        const m = /^conv-(\d+)$/.exec(h);
        if (!m) return;
        const id = parseInt(m[1], 10);
        if (!id) return;
        let c = this.state.conversations.find((x) => x.id === id);
        if (!c) {
            await this.loadList();
            c = this.state.conversations.find((x) => x.id === id);
        }
        if (c) {
            this.openConversation(id);
        }
    },

    async loadList() {
        const f = document.getElementById('inbox-filter')?.value || 'all';
        try {
            const res = await API.get(`/api/conversations?filter=${encodeURIComponent(f)}`);
            this.state.conversations = res.data?.conversations || [];
            this.renderList();
        } catch (e) {
            console.warn(e);
        }
    },

    renderList() {
        const el = document.getElementById('inbox-list');
        if (!el) return;
        if (!this.state.conversations.length) {
            el.innerHTML = '<p class="p-4 text-center text-sm text-slate-500">Nenhuma conversa</p>';
            return;
        }
        el.innerHTML = this.state.conversations
            .map((c) => {
                const active = c.id === this.state.activeId ? 'bg-primary-50 ring-1 ring-primary-200' : 'hover:bg-slate-50';
                const prev = (c.last_message_preview || '').replace(/</g, '&lt;');
                const unread = c.unread_count > 0 ? `<span class="ml-1 rounded-full bg-primary-600 px-1.5 text-[10px] text-white">${c.unread_count}</span>` : '';
                return `<button type="button" class="flex w-full flex-col items-start rounded-lg px-3 py-2 text-left text-sm ${active}" data-cid="${c.id}">
                    <span class="font-medium text-slate-900">${this.escape(c.contact_name || c.contact_push_name || c.contact_phone)}${unread}</span>
                    <span class="line-clamp-2 text-xs text-slate-500">${prev || '—'}</span>
                </button>`;
            })
            .join('');

        el.querySelectorAll('[data-cid]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-cid'), 10);
                this.openConversation(id);
            });
        });
    },

    escape(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    },

    openConversation(id) {
        this.state.activeId = id;
        if (this.state.pollMsg) clearInterval(this.state.pollMsg);
        this.renderList();

        const c = this.state.conversations.find((x) => x.id === id);
        const titleEl = document.getElementById('inbox-chat-title');
        const subEl = document.getElementById('inbox-chat-sub');
        if (titleEl) {
            titleEl.textContent = c ? c.contact_name || c.contact_push_name || c.contact_phone : 'Conversa';
        }
        if (subEl) {
            subEl.textContent = c?.lead_name ? `Lead: ${c.lead_name}` : '';
        }

        const ta = document.getElementById('inbox-text');
        const snd = document.getElementById('inbox-send');
        if (ta) ta.disabled = false;
        if (snd) snd.disabled = false;

        this.refreshMessages();
        this.state.pollMsg = setInterval(() => this.refreshMessages(), 3000);
    },

    async refreshMessages() {
        if (!this.state.activeId) return;
        try {
            const res = await API.get(`/api/conversations/${this.state.activeId}/messages`);
            const msgs = res.data?.messages || [];
            const box = document.getElementById('inbox-messages');
            if (!box) return;

            // Verificar se usuario esta proximo do fim (para nao interromper leitura)
            const isNearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 50;

            if (!msgs.length) {
                box.innerHTML = '<p class="text-center text-sm text-slate-500">Sem mensagens</p>';
                return;
            }
            box.innerHTML = msgs
                .map((m) => {
                    const out = m.direction === 'outbound';
                    const media =
                        m.media_url && String(m.media_url).startsWith('http')
                            ? `<div class="mt-1 text-xs opacity-80"><a href="${this.escape(m.media_url)}" target="_blank" rel="noopener noreferrer" class="underline">Abrir midia</a></div>`
                            : '';
                    return `<div class="flex ${out ? 'justify-end' : 'justify-start'}"><div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm ${out ? 'bg-primary-600 text-white' : 'bg-white text-slate-800 ring-1 ring-slate-200'}">${this.escape(m.content || '')}${media}<div class="mt-1 text-[10px] opacity-70">${this.escape(m.created_at || '')} · ${this.escape(m.status || '')}</div></div></div>`;
                })
                .join('');

            // So rolar automaticamente se usuario estava proximo do fim
            if (isNearBottom) {
                box.scrollTop = box.scrollHeight;
            }
        } catch (e) {
            console.warn(e);
        }
    },

    async send() {
        const t = document.getElementById('inbox-text');
        const text = (t?.value || '').trim();
        if (!this.state.activeId || !text) return;
        try {
            await API.post(`/api/conversations/${this.state.activeId}/messages`, { text });
            t.value = '';
            await this.refreshMessages();
        } catch (e) {
            alert(e.message || 'Erro ao enviar');
        }
    },
};

document.addEventListener('DOMContentLoaded', () => Inbox.init());
window.Inbox = Inbox;
