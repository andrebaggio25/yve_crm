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
        recording: false,
        recInterval: null,
        recStartedAt: 0,
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
        const inboxTa = document.getElementById('inbox-text');
        inboxTa?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        });
        inboxTa?.addEventListener('input', () => this.syncComposer());
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
        document.getElementById('inbox-mic')?.addEventListener('click', () => this.onMicButton());

        document.getElementById('inbox-rec-cancel')?.addEventListener('click', () => this.cancelRecording());
        document.getElementById('inbox-media-cancel')?.addEventListener('click', () => this.closeMediaModal());
        document.getElementById('inbox-media-dismiss')?.addEventListener('click', () => this.closeMediaModal());
        document.getElementById('inbox-media-confirm')?.addEventListener('click', () => this.confirmSendMedia());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (this.state.recording) {
                    this.cancelRecording();
                    return;
                }
                this.closeLightbox();
                this.closeMediaModal();
            }
        });

        document.getElementById('inbox-media-modal')?.addEventListener('click', (e) => {
            if (e.target && e.target.id === 'inbox-media-modal') {
                this.closeMediaModal();
            }
        });

        (async () => {
            await this.loadList();
            await this.openFromHash();
        })();
        this.state.pollList = setInterval(() => this.loadList(), 10000);
        window.addEventListener('hashchange', () => this.openFromHash());
        this.syncComposer();
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

    /** Enviar visivel so com texto; microfone quando campo vazio (estilo WhatsApp). */
    syncComposer() {
        const ta = document.getElementById('inbox-text');
        const send = document.getElementById('inbox-send');
        const mic = document.getElementById('inbox-mic');
        if (!ta || !send || !mic) return;
        if (this.state.recording) {
            send.classList.add('hidden');
            mic.classList.remove('hidden');
            return;
        }
        const has = (ta.value || '').trim().length > 0;
        if (has) {
            send.classList.remove('hidden');
            mic.classList.add('hidden');
        } else {
            send.classList.add('hidden');
            mic.classList.remove('hidden');
        }
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

    renderMediaBlock(m, out, compact) {
        const url = this.mediaUrl(m);
        const type = String(m.type || 'text');
        const topPad = compact ? 'mt-0' : 'mt-2';
        if (!url) {
            if (type !== 'text' && type !== 'unknown') {
                return '<p class="mt-1 text-xs opacity-80">Midia indisponivel</p>';
            }
            return '';
        }

        const isApi = url.startsWith('/api/messages/');
        const isHttp = /^https?:\/\//i.test(url);

        if (type === 'image' || type === 'sticker') {
            // Imagens compactas para desktop e mobile
            const cls = type === 'sticker'
                ? 'h-auto max-h-24 w-auto max-w-[140px] cursor-pointer rounded-lg object-contain'
                : 'h-auto max-h-[140px] w-auto max-w-[200px] cursor-pointer rounded-lg object-cover';
            const safe = this.escape(url);
            return `<div class="${topPad} overflow-hidden rounded-lg">
                <img src="${safe}" alt="" loading="lazy" class="${cls}" data-inbox-lightbox="${safe}" referrerpolicy="no-referrer" />
            </div>`;
        }

        if (type === 'video') {
            const safe = this.escape(url);
            return `<div class="${topPad} max-w-full overflow-hidden rounded-lg">
                <video controls class="max-h-64 w-full rounded-lg bg-black/5" preload="metadata" src="${safe}"></video>
            </div>`;
        }

        if (type === 'audio') {
            const ptt = Number(m.media_ptt) === 1;
            const pttBadge = ptt
                ? `<span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide ${out ? 'bg-white/30 text-white' : 'bg-primary-100 text-primary-700'}">voz</span>`
                : '';
            // Player com SVG no botao de play
            const playIcon = `<svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z"/></svg>`;
            const outCls = out ? 'inbox-audio-out' : '';
            const border = out ? 'border-white/30 bg-black/20' : 'border-slate-200 bg-white';
            const pad = compact ? 'px-2 py-1' : 'px-2 py-1.5';
            return `<div class="inbox-audio-player ${topPad} w-full max-w-[260px] rounded-full border ${border} ${pad} ${outCls}" data-inbox-audio data-audio-src="${this.escape(url)}">
                <div class="flex items-center gap-2">
                    <button type="button" data-play class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${out ? 'bg-white text-primary-600' : 'bg-primary-600 text-white'} shadow-sm">${playIcon}</button>
                    <div class="min-h-[28px] min-w-0 flex-1" data-waveform></div>
                    ${pttBadge}
                    <span data-time class="shrink-0 font-mono text-[10px] tabular-nums opacity-90">0:00</span>
                    <button type="button" data-rate class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${out ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-700'}">1×</button>
                </div>
            </div>`;
        }

        if (type === 'document') {
            const name = m.media_filename ? String(m.media_filename) : 'Documento';
            const sz = this.formatBytes(m.media_size_bytes);
            const meta = sz ? `<span class="text-[10px] opacity-80">${this.escape(sz)}</span>` : '';
            // SVG simples de documento
            const iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-8 w-8 shrink-0 text-primary-600">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
                <polyline points="14 2 14 8 20 8" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="16" y1="13" x2="8" y2="13" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="16" y1="17" x2="8" y2="17" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
            return `<a href="${this.escape(url)}" target="_blank" rel="noopener noreferrer" class="${topPad} flex max-w-[260px] items-center gap-2 rounded-xl border ${out ? 'border-white/40 bg-white/95 text-slate-900' : 'border-slate-200 bg-white text-slate-900'} px-3 py-2 text-left text-xs shadow-sm">
                ${iconSvg}
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
        wrap.className = 'fixed inset-0 z-[70] hidden items-center justify-center bg-black/95 p-4 backdrop-blur-sm';
        wrap.innerHTML = `
            <button type="button" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-2xl text-white hover:bg-white/30" aria-label="Fechar">&times;</button>
            <img alt="" class="max-h-[90vh] max-w-[90vw] cursor-zoom-out rounded-lg object-contain shadow-2xl" />
        `;
        document.body.appendChild(wrap);
        this.state.lightboxEl = wrap;
        this.state.lightboxImg = wrap.querySelector('img');
        this.state.lightboxClose = wrap.querySelector('button');
        wrap.addEventListener('click', (e) => {
            if (e.target === wrap || e.target === this.state.lightboxClose || e.target === this.state.lightboxImg) {
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

        this.syncComposer();
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
                    const text = (m.content || '').trim();
                    const t = String(m.type || 'text');
                    const onlyMedia =
                        !text && ['image', 'sticker', 'video', 'audio', 'document'].includes(t);
                    const media = this.renderMediaBlock(m, out, onlyMedia);
                    const bubble = out
                        ? 'bg-primary-600 text-white rounded-tr-sm'
                        : 'bg-white text-slate-800 ring-1 ring-slate-200 rounded-tl-sm';
                    const bubblePad = onlyMedia && t === 'audio' ? 'px-2.5 py-1' : 'px-3 py-2';
                    const time = this.formatTime(m.created_at);
                    const statusDot = out ? `<span class="opacity-70">· ${this.escape(m.status || '')}</span>` : '';
                    const textHtml = text ? `<div class="whitespace-pre-wrap break-words">${this.escape(text)}</div>` : '';
                    const metaPad = onlyMedia && t === 'audio' ? 'mt-0.5' : 'mt-1';
                    return `<div class="flex ${out ? 'justify-end' : 'justify-start'}">
                        <div class="max-w-[min(100%,20rem)] rounded-2xl ${bubblePad} text-sm shadow-sm ${bubble}">
                            ${textHtml}
                            ${media}
                            <div class="${metaPad} flex items-center justify-end gap-1 text-[10px] opacity-70">
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
            this.syncComposer();
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
        const title = document.getElementById('inbox-media-modal-title');
        if (title) {
            title.textContent = isVoice ? 'Enviar audio' : 'Enviar arquivo';
        }
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
        const ct = p.file.type || '';
        if (ct) fd.append('client_mime', ct);
        console.info('[InboxMedia] enviando', {
            conv: this.state.activeId,
            name: p.file.name,
            size: p.file.size,
            type: ct,
            isVoice: !!p.isVoice,
        });
        try {
            await API.postForm(`/api/conversations/${this.state.activeId}/media`, fd);
            this.closeMediaModal();
            await this.refreshMessages();
            await this.loadList();
        } catch (e) {
            console.error('[InboxMedia] falha', e, e?.data);
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

    clearRecTimer() {
        if (this.state.recInterval) {
            clearInterval(this.state.recInterval);
            this.state.recInterval = null;
        }
    },

    setRecordingUi(active) {
        const ta = document.getElementById('inbox-text');
        const bar = document.getElementById('inbox-recording-bar');
        const att = document.getElementById('inbox-attach');
        if (ta) ta.classList.toggle('hidden', active);
        if (bar) {
            bar.classList.toggle('hidden', !active);
            bar.classList.toggle('flex', active);
            bar.classList.toggle('flex-col', active);
        }
        if (att) att.disabled = !!active;
        this.syncComposer();
    },

    tickRecTimer() {
        const el = document.getElementById('inbox-rec-timer');
        if (!el) return;
        const ms = Date.now() - this.state.recStartedAt;
        const s = Math.floor(ms / 1000);
        const m = Math.floor(s / 60);
        const r = s % 60;
        el.textContent = `${m}:${String(r).padStart(2, '0')}`;
    },

    onMicButton() {
        if (!this.state.activeId) return;
        if (this.state.mediaRecorder && this.state.mediaRecorder.state === 'recording') {
            this.stopRecording();
            return;
        }
        this.startRecording();
    },

    async startRecording() {
        const mime = this.pickRecorderMime();
        if (!mime || !navigator.mediaDevices?.getUserMedia) {
            console.warn('[InboxMedia] gravacao indisponivel', { mime, hasGum: !!navigator.mediaDevices?.getUserMedia });
            alert('Gravacao de audio nao suportada neste navegador.');
            return;
        }
        console.info('[InboxMedia] iniciando gravacao', { mime });
        const micBtn = document.getElementById('inbox-mic');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.state.recordedChunks = [];
            this.state.recordingMime = mime;
            this.state.recording = true;
            this.state.recStartedAt = Date.now();
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
                this.state.recording = false;
                this.clearRecTimer();
                this.setRecordingUi(false);
                if (micBtn) {
                    micBtn.classList.remove('ring-2', 'ring-red-500', 'bg-red-50', 'text-red-600');
                }
                console.info('[InboxMedia] gravacao parada', { bytes: blob.size, mime: this.state.recordingMime });
                if (blob.size === 0) {
                    console.warn('[InboxMedia] blob vazio — nada a enviar');
                    alert('Gravacao vazia. Tente novamente.');
                    return;
                }
                this.openMediaModal(f, true);
            };
            rec.start(250);
            this.setRecordingUi(true);
            this.clearRecTimer();
            this.tickRecTimer();
            this.state.recInterval = setInterval(() => this.tickRecTimer(), 250);
            if (micBtn) {
                micBtn.classList.add('ring-2', 'ring-red-500', 'bg-red-50', 'text-red-600');
            }
        } catch (err) {
            console.error('[InboxMedia] getUserMedia', err);
            alert('Nao foi possivel acessar o microfone.');
        }
    },

    stopRecording() {
        const rec = this.state.mediaRecorder;
        if (rec && rec.state === 'recording') {
            rec.stop();
        }
    },

    cancelRecording() {
        console.info('[InboxMedia] gravacao cancelada');
        const rec = this.state.mediaRecorder;
        if (rec) {
            const stream = rec.stream;
            rec.onstop = () => {
                stream?.getTracks().forEach((t) => t.stop());
            };
            this.state.recordedChunks = [];
            if (rec.state === 'recording') {
                rec.stop();
            } else {
                stream?.getTracks().forEach((t) => t.stop());
            }
            this.state.mediaRecorder = null;
        }
        this.state.recording = false;
        this.clearRecTimer();
        this.setRecordingUi(false);
        const micBtn = document.getElementById('inbox-mic');
        if (micBtn) {
            micBtn.classList.remove('ring-2', 'ring-red-500', 'bg-red-50', 'text-red-600');
        }
    },
};

document.addEventListener('DOMContentLoaded', () => Inbox.init());
window.Inbox = Inbox;
