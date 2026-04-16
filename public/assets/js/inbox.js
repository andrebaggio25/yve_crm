/**
 * Inbox WhatsApp — lista, chat e polling.
 * Suporta avatar (foto de perfil WhatsApp) quando disponivel.
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
        document.getElementById('inbox-text')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        });
        document.getElementById('inbox-search')?.addEventListener('input', () => this.renderList());

        (async () => {
            await this.loadList();
            await this.openFromHash();
        })();
        this.state.pollList = setInterval(() => this.loadList(), 10000);
        window.addEventListener('hashchange', () => this.openFromHash());
    },

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

    displayName(c) {
        return c.contact_name || c.contact_push_name || c.lead_name || c.contact_phone || 'Sem nome';
    },

    initials(name) {
        const s = String(name || '').trim();
        if (!s) return '?';
        const parts = s.split(/\s+/).filter(Boolean);
        const first = parts[0]?.[0] ?? '';
        const second = parts.length > 1 ? parts[parts.length - 1][0] : '';
        return (first + second).toUpperCase() || s[0].toUpperCase();
    },

    colorForName(name) {
        const palette = [
            'bg-rose-500', 'bg-amber-500', 'bg-emerald-500', 'bg-sky-500',
            'bg-indigo-500', 'bg-fuchsia-500', 'bg-teal-500', 'bg-orange-500',
        ];
        const s = String(name || '');
        let hash = 0;
        for (let i = 0; i < s.length; i++) hash = (hash * 31 + s.charCodeAt(i)) >>> 0;
        return palette[hash % palette.length];
    },

    avatarHTML(c, size = 'md') {
        const name = this.displayName(c);
        const dim = size === 'lg' ? 'h-10 w-10 text-sm' : 'h-9 w-9 text-xs';
        const url = c.contact_avatar_url;
        if (url) {
            return `<img src="${this.escape(url)}" alt="${this.escape(name)}" class="${dim} flex-none rounded-full object-cover ring-1 ring-slate-200" referrerpolicy="no-referrer" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'${dim} flex-none rounded-full text-white font-semibold inline-flex items-center justify-center ${this.colorForName(name)}',textContent:'${this.initials(name)}'}))" />`;
        }
        const color = this.colorForName(name);
        return `<span class="${dim} flex-none rounded-full text-white font-semibold inline-flex items-center justify-center ${color}">${this.escape(this.initials(name))}</span>`;
    },

    formatTime(iso) {
        if (!iso) return '';
        const d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        if (sameDay) {
            return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        const yest = new Date(now);
        yest.setDate(now.getDate() - 1);
        if (d.toDateString() === yest.toDateString()) return 'Ontem';
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    },

    renderList() {
        const el = document.getElementById('inbox-list');
        if (!el) return;
        const q = (document.getElementById('inbox-search')?.value || '').toLowerCase().trim();
        let items = this.state.conversations;
        if (q) {
            items = items.filter((c) => {
                const hay = [c.contact_name, c.contact_push_name, c.lead_name, c.contact_phone, c.last_message_preview]
                    .map((x) => String(x || '').toLowerCase())
                    .join(' ');
                return hay.includes(q);
            });
        }

        if (!items.length) {
            el.innerHTML = '<p class="p-4 text-center text-sm text-slate-500">Nenhuma conversa</p>';
            return;
        }

        el.innerHTML = items
            .map((c) => {
                const active = c.id === this.state.activeId ? 'bg-primary-50 ring-1 ring-primary-200' : 'hover:bg-slate-50';
                const name = this.displayName(c);
                const prev = (c.last_message_preview || '').replace(/\s+/g, ' ').trim();
                const unread = c.unread_count > 0
                    ? `<span class="ml-auto min-w-[20px] rounded-full bg-primary-600 px-1.5 py-[2px] text-center text-[10px] font-semibold text-white">${c.unread_count}</span>`
                    : '';
                const time = this.formatTime(c.last_message_at);
                return `<button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition-colors ${active}" data-cid="${c.id}">
                    ${this.avatarHTML(c, 'lg')}
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="flex items-center gap-2">
                            <span class="truncate font-medium text-slate-900">${this.escape(name)}</span>
                            ${time ? `<span class="ml-auto shrink-0 text-[10px] font-normal text-slate-400">${this.escape(time)}</span>` : ''}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="line-clamp-1 flex-1 text-xs text-slate-500">${this.escape(prev) || '—'}</span>
                            ${unread}
                        </span>
                    </span>
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
        const avatarEl = document.getElementById('inbox-chat-avatar');
        const titleEl = document.getElementById('inbox-chat-title');
        const subEl = document.getElementById('inbox-chat-sub');
        if (avatarEl) avatarEl.innerHTML = c ? this.avatarHTML(c, 'lg') : '';
        if (titleEl) {
            titleEl.textContent = c ? this.displayName(c) : 'Conversa';
        }
        if (subEl) {
            const lead = c?.lead_name ? `Lead: ${c.lead_name}` : '';
            const phone = c?.contact_phone && !String(c.contact_phone).startsWith('lid:') ? `· ${c.contact_phone}` : '';
            subEl.textContent = [lead, phone].filter(Boolean).join(' ');
        }

        const ta = document.getElementById('inbox-text');
        const snd = document.getElementById('inbox-send');
        if (ta) {
            ta.disabled = false;
            ta.focus();
        }
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
                    const bubble = out
                        ? 'bg-primary-600 text-white rounded-tr-sm'
                        : 'bg-white text-slate-800 ring-1 ring-slate-200 rounded-tl-sm';
                    const time = this.formatTime(m.created_at);
                    const statusDot = out ? `<span class="opacity-70">· ${this.escape(m.status || '')}</span>` : '';
                    return `<div class="flex ${out ? 'justify-end' : 'justify-start'}">
                        <div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm shadow-sm ${bubble}">
                            <div class="whitespace-pre-wrap break-words">${this.escape(m.content || '')}</div>
                            ${media}
                            <div class="mt-1 flex items-center justify-end gap-1 text-[10px] opacity-70">
                                <span>${this.escape(time)}</span>
                                ${statusDot}
                            </div>
                        </div>
                    </div>`;
                })
                .join('');

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
            await this.loadList();
        } catch (e) {
            alert(e.message || 'Erro ao enviar');
        }
    },
};

document.addEventListener('DOMContentLoaded', () => Inbox.init());
window.Inbox = Inbox;
