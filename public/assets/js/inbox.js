/**
 * Inbox WhatsApp — lista, chat, polling e midia (receber/enviar).
 */
const Inbox = {
    state: {
        conversations: [],
        activeId: null,
        pollList: null,
        pollMsg: null,
        pendingMedia: null,
        mediaRecorder: null,
        recordedChunks: [],
        recordingMime: '',
        lightboxEl: null,
        lightboxImg: null,
        lightboxClose: null,
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

        document.getElementById('inbox-attach')?.addEventListener('click', () => {
            document.getElementById('inbox-file')?.click();
        });
        document.getElementById('inbox-file')?.addEventListener('change', (e) => {
            const f = e.target.files?.[0];
            if (f) {
                this.openMediaModal(f, false);
            }
            e.target.value = '';
        });
        document.getElementById('inbox-mic')?.addEventListener('click', () => this.toggleMic());

        document.getElementById('inbox-media-cancel')?.addEventListener('click', () => this.closeMediaModal());
        document.getElementById('inbox-media-confirm')?.addEventListener('click', () => this.confirmSendMedia());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeLightbox();
                this.closeMediaModal();
            }
        });

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

    formatBytes(n) {
        const x = Number(n);
        if (!isFinite(x) || x < 0) return '';
        if (x < 1024) return x + ' B';
        if (x < 1024 * 1024) return (x / 1024).toFixed(1) + ' KB';
        return (x / (1024 * 1024)).toFixed(1) + ' MB';
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

    mediaUrl(m) {
        const u = m.media_url;
        if (!u) return '';
        return String(u);
    },

    renderMediaBlock(m, out) {
        const url = this.mediaUrl(m);
        const type = String(m.type || 'text');
        if (!url) {
            if (type !== 'text' && type !== 'unknown') {
                return '<p class="mt-1 text-xs opacity-80">Midia indisponivel</p>';
            }
            return '';
        }

        const isApi = url.startsWith('/api/messages/');

        if (type === 'image' || type === 'sticker') {
            const cls = type === 'sticker'
                ? 'max-h-36 w-auto max-w-[200px] cursor-pointer rounded-lg object-contain'
                : 'max-h-56 max-w-full cursor-pointer rounded-lg object-cover';
            const safe = this.escape(url);
            return `<div class="mt-2 overflow-hidden rounded-lg">
                <img src="${safe}" alt="" loading="lazy" class="${cls}" data-inbox-lightbox="${safe}" referrerpolicy="no-referrer" />
            </div>`;
        }

        if (type === 'video') {
            const safe = this.escape(url);
            return `<div class="mt-2 max-w-full overflow-hidden rounded-lg">
                <video controls class="max-h-64 w-full rounded-lg bg-black/5" preload="metadata" src="${safe}"></video>
            </div>`;
        }

        if (type === 'audio') {
            const ptt = Number(m.media_ptt) === 1;
            const pttBadge = ptt
                ? `<span class="mb-1 inline-block rounded-md ${out ? 'bg-white/20 text-white' : 'bg-primary-100 text-primary-800'} px-2 py-0.5 text-[10px] font-medium">Mensagem de voz</span>`
                : '';
            if (isApi && typeof window.WaveSurfer !== 'undefined') {
                const outCls = out ? 'inbox-audio-out' : '';
                const border = out ? 'border-white/25 bg-white/10' : 'border-slate-200 bg-slate-50';
                return `<div class="inbox-audio-player mt-2 max-w-full rounded-xl border ${border} px-2 py-2 ${outCls}" data-inbox-audio data-audio-src="${this.escape(url)}">
                    ${pttBadge}
                    <div class="flex items-center gap-2">
                        <button type="button" data-play class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${out ? 'bg-white/20 text-white' : 'bg-primary-600 text-white'} text-sm">▶</button>
                        <div class="min-h-[36px] min-w-0 flex-1" data-waveform></div>
                        <span data-time class="shrink-0 font-mono text-[10px] opacity-80">0:00</span>
                        <button type="button" data-rate class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium ${out ? 'bg-white/15 text-white' : 'bg-slate-200 text-slate-700'}">1×</button>
                    </div>
                </div>`;
            }
            return `<div class="mt-2">${pttBadge}<audio controls class="h-9 w-full max-w-[280px]" preload="metadata" src="${this.escape(url)}"></audio></div>`;
        }

        if (type === 'document') {
            const name = m.media_filename ? String(m.media_filename) : 'Documento';
            const sz = this.formatBytes(m.media_size_bytes);
            const meta = sz ? `<span class="text-[10px] opacity-70">${this.escape(sz)}</span>` : '';
            return `<a href="${this.escape(url)}" target="_blank" rel="noopener noreferrer" class="mt-2 flex max-w-full items-center gap-2 rounded-xl border ${out ? 'border-white/30 bg-white/10' : 'border-slate-200 bg-white'} px-3 py-2 text-xs shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 shrink-0 opacity-70"><path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625ZM7.5 15a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 7.5 15Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H8.25Z" clip-rule="evenodd" /><path d="M14.25 5.25a3 3 0 0 0 3 3h3.75a3 3 0 0 0-3-3h-3.75Z" /></svg>
                <span class="min-w-0 flex-1 truncate font-medium">${this.escape(name)}</span>
                ${meta}
            </a>`;
        }

        if (isHttp) {
            return `<div class="mt-1 text-xs opacity-80"><a href="${this.escape(url)}" target="_blank" rel="noopener noreferrer" class="underline">Abrir midia</a></div>`;
        }

        return '';
    },

    bindLightbox(root) {
        root.querySelectorAll('[data-inbox-lightbox]').forEach((img) => {
            img.addEventListener('click', () => {
                const src = img.getAttribute('data-inbox-lightbox');
                if (src) this.openLightbox(src);
            });
        });
    },

    ensureLightbox() {
        if (this.state.lightboxEl) return;
        const wrap = document.createElement('div');
        wrap.id = 'inbox-lightbox';
        wrap.className = 'fixed inset-0 z-[70] hidden items-center justify-center bg-black/92 p-4';
        wrap.innerHTML = '<button type="button" class="absolute right-3 top-3 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Fechar">&times;</button><img alt="" class="max-h-full max-w-full object-contain" />';
        document.body.appendChild(wrap);
        this.state.lightboxEl = wrap;
        this.state.lightboxImg = wrap.querySelector('img');
        this.state.lightboxClose = wrap.querySelector('button');
        wrap.addEventListener('click', (e) => {
            if (e.target === wrap || e.target === this.state.lightboxClose) {
                this.closeLightbox();
            }
        });
    },

    openLightbox(src) {
        this.ensureLightbox();
        if (this.state.lightboxImg) {
            this.state.lightboxImg.src = src;
        }
        this.state.lightboxEl.classList.remove('hidden');
        this.state.lightboxEl.classList.add('flex');
    },

    closeLightbox() {
        if (this.state.lightboxEl) {
            this.state.lightboxEl.classList.add('hidden');
            this.state.lightboxEl.classList.remove('flex');
        }
    },

    openConversation(id) {
        this.state.activeId = id;
        if (this.state.pollMsg) clearInterval(this.state.pollMsg);
        this.renderList();

        const box = document.getElementById('inbox-messages');
        if (box && window.InboxAudioPlayer) {
            window.InboxAudioPlayer.destroyIn(box);
        }

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
        const att = document.getElementById('inbox-attach');
        const mic = document.getElementById('inbox-mic');
        if (ta) {
            ta.disabled = false;
            ta.focus();
        }
        if (snd) snd.disabled = false;
        if (att) att.disabled = false;
        if (mic) mic.disabled = false;

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

            if (window.InboxAudioPlayer) {
                window.InboxAudioPlayer.destroyIn(box);
            }

            const isNearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 50;

            if (!msgs.length) {
                box.innerHTML = '<p class="text-center text-sm text-slate-500">Sem mensagens</p>';
                return;
            }

            box.innerHTML = msgs
                .map((m) => {
                    const out = m.direction === 'outbound';
                    const media = this.renderMediaBlock(m, out);
                    const bubble = out
                        ? 'bg-primary-600 text-white rounded-tr-sm'
                        : 'bg-white text-slate-800 ring-1 ring-slate-200 rounded-tl-sm';
                    const time = this.formatTime(m.created_at);
                    const statusDot = out ? `<span class="opacity-70">· ${this.escape(m.status || '')}</span>` : '';
                    const text = (m.content || '').trim();
                    const textHtml = text ? `<div class="whitespace-pre-wrap break-words">${this.escape(text)}</div>` : '';
                    return `<div class="flex ${out ? 'justify-end' : 'justify-start'}">
                        <div class="max-w-[min(100%,28rem)] rounded-2xl px-3 py-2 text-sm shadow-sm ${bubble}">
                            ${textHtml}
                            ${media}
                            <div class="mt-1 flex items-center justify-end gap-1 text-[10px] opacity-70">
                                <span>${this.escape(time)}</span>
                                ${statusDot}
                            </div>
                        </div>
                    </div>`;
                })
                .join('');

            if (window.InboxAudioPlayer) {
                window.InboxAudioPlayer.mountAll(box);
            }
            this.bindLightbox(box);

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

    openMediaModal(file, isVoice) {
        if (!this.state.activeId) return;
        this.state.pendingMedia = { file, isVoice };
        const modal = document.getElementById('inbox-media-modal');
        const prev = document.getElementById('inbox-media-preview');
        const cap = document.getElementById('inbox-media-caption');
        if (cap) cap.value = '';
        if (prev) {
            prev.innerHTML = '';
            const type = file.type || '';
            const url = URL.createObjectURL(file);
            if (type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = url;
                img.className = 'max-h-48 w-full rounded-lg object-contain';
                img.onload = () => URL.revokeObjectURL(url);
                prev.appendChild(img);
            } else if (type.startsWith('video/')) {
                const v = document.createElement('video');
                v.src = url;
                v.controls = true;
                v.className = 'max-h-48 w-full rounded-lg';
                prev.appendChild(v);
            } else if (type.startsWith('audio/')) {
                const a = document.createElement('audio');
                a.src = url;
                a.controls = true;
                a.className = 'w-full';
                prev.appendChild(a);
            } else {
                const p = document.createElement('p');
                p.className = 'text-sm text-slate-600';
                p.textContent = file.name || 'Arquivo anexado';
                prev.appendChild(p);
                URL.revokeObjectURL(url);
            }
        }
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
        }
    },

    closeMediaModal() {
        this.state.pendingMedia = null;
        const modal = document.getElementById('inbox-media-modal');
        const prev = document.getElementById('inbox-media-preview');
        if (prev) prev.innerHTML = '';
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
        }
    },

    async confirmSendMedia() {
        const p = this.state.pendingMedia;
        if (!p || !this.state.activeId) {
            this.closeMediaModal();
            return;
        }
        const capEl = document.getElementById('inbox-media-caption');
        const caption = (capEl?.value || '').trim();
        const fd = new FormData();
        fd.append('file', p.file, p.file.name || 'upload');
        if (caption) fd.append('caption', caption);
        if (p.isVoice) fd.append('is_voice_note', '1');
        try {
            await API.postForm(`/api/conversations/${this.state.activeId}/media`, fd);
            this.closeMediaModal();
            await this.refreshMessages();
            await this.loadList();
        } catch (e) {
            alert(e.message || 'Erro ao enviar midia');
        }
    },

    pickRecorderMime() {
        if (typeof MediaRecorder === 'undefined') return '';
        if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) return 'audio/ogg;codecs=opus';
        if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) return 'audio/webm;codecs=opus';
        if (MediaRecorder.isTypeSupported('audio/webm')) return 'audio/webm';
        return '';
    },

    extForMime(mime) {
        if (mime.includes('ogg')) return 'ogg';
        if (mime.includes('webm')) return 'webm';
        return 'bin';
    },

    async toggleMic() {
        if (!this.state.activeId) return;
        if (this.state.mediaRecorder && this.state.mediaRecorder.state === 'recording') {
            this.state.mediaRecorder.stop();
            return;
        }
        const mime = this.pickRecorderMime();
        if (!mime || !navigator.mediaDevices?.getUserMedia) {
            alert('Gravacao de audio nao suportada neste navegador.');
            return;
        }
        const btn = document.getElementById('inbox-mic');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.state.recordedChunks = [];
            this.state.recordingMime = mime;
            const rec = new MediaRecorder(stream, { mimeType: mime });
            this.state.mediaRecorder = rec;
            rec.ondataavailable = (e) => {
                if (e.data.size > 0) this.state.recordedChunks.push(e.data);
            };
            rec.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(this.state.recordedChunks, { type: this.state.recordingMime });
                const ext = this.extForMime(this.state.recordingMime);
                const f = new File([blob], `gravacao.${ext}`, { type: this.state.recordingMime });
                this.state.mediaRecorder = null;
                if (btn) {
                    btn.classList.remove('animate-pulse', 'border-red-400', 'text-red-600', 'bg-red-50');
                }
                if (blob.size > 0) {
                    this.openMediaModal(f, true);
                }
            };
            rec.start();
            if (btn) {
                btn.classList.add('animate-pulse', 'border-red-400', 'text-red-600', 'bg-red-50');
            }
        } catch (err) {
            console.warn(err);
            alert('Nao foi possivel acessar o microfone.');
        }
    },
};

document.addEventListener('DOMContentLoaded', () => Inbox.init());
window.Inbox = Inbox;
