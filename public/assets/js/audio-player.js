/**
 * Players de audio com WaveSurfer (inbox).
 */
(function (global) {
    'use strict';

    const SPEEDS = [1, 1.5, 2];
    const instances = new WeakMap();

    function formatTime(sec) {
        if (!isFinite(sec) || sec < 0) {
            return '0:00';
        }
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return m + ':' + String(s).padStart(2, '0');
    }

    global.InboxAudioPlayer = {
        mountAll(root) {
            const el = root || document;
            if (typeof global.WaveSurfer === 'undefined') {
                console.warn('[AudioPlayer] WaveSurfer nao carregado');
                return;
            }
            el.querySelectorAll('[data-inbox-audio]:not([data-inbox-audio-mounted])').forEach((wrap) => {
                wrap.setAttribute('data-inbox-audio-mounted', '1');
                this.mountOne(wrap);
            });
        },

        mountOne(wrap) {
            const src = wrap.getAttribute('data-audio-src');
            console.log('[AudioPlayer] montando player', { src });
            
            if (!src) {
                console.warn('[AudioPlayer] sem src');
                return;
            }
            if (typeof global.WaveSurfer === 'undefined') {
                console.warn('[AudioPlayer] WaveSurfer nao disponivel, usando fallback');
                this.fallbackAudio(wrap, src, wrap.classList.contains('inbox-audio-out'));
                return;
            }

            const out = wrap.classList.contains('inbox-audio-out');
            const wfEl = wrap.querySelector('[data-waveform]');
            const playBtn = wrap.querySelector('[data-play]');
            const timeEl = wrap.querySelector('[data-time]');
            const rateBtn = wrap.querySelector('[data-rate]');
            
            if (!wfEl || !playBtn) {
                console.warn('[AudioPlayer] elementos nao encontrados', { wfEl: !!wfEl, playBtn: !!playBtn });
                return;
            }

            const waveColor = out ? 'rgba(255,255,255,0.4)' : 'rgba(15,23,42,0.25)';
            const progColor = out ? '#ffffff' : '#0d9488';

            let speedIdx = 0;
            let ws;
            let loadTimeout;

            try {
                ws = global.WaveSurfer.create({
                    container: wfEl,
                    waveColor: waveColor,
                    progressColor: progColor,
                    cursorColor: progColor,
                    barWidth: 2,
                    barRadius: 2,
                    height: 28,
                    normalize: true,
                    backend: 'WebAudio',
                });
                instances.set(wrap, ws);
                console.log('[AudioPlayer] WaveSurfer criado');
            } catch (e) {
                console.error('[AudioPlayer] erro ao criar WaveSurfer', e);
                this.fallbackAudio(wrap, src, out);
                return;
            }

            // Timeout de seguranca - se nao carregar em 5s, usa fallback
            loadTimeout = setTimeout(() => {
                console.warn('[AudioPlayer] timeout ao carregar audio, usando fallback');
                this.fallbackAudio(wrap, src, out);
                try { ws.destroy(); } catch (e) {}
            }, 5000);

            ws.on('ready', () => {
                clearTimeout(loadTimeout);
                console.log('[AudioPlayer] audio pronto, duracao:', ws.getDuration());
                if (timeEl) {
                    timeEl.textContent = formatTime(ws.getDuration());
                }
            });

            ws.on('error', (err) => {
                clearTimeout(loadTimeout);
                console.error('[AudioPlayer] erro ao carregar audio', src, err);
                this.fallbackAudio(wrap, src, out);
            });

            ws.on('audioprocess', () => {
                if (timeEl) {
                    timeEl.textContent = formatTime(ws.getCurrentTime()) + ' / ' + formatTime(ws.getDuration());
                }
            });

            ws.on('play', () => {
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>';
            });

            ws.on('pause', () => {
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z"/></svg>';
            });

            ws.on('finish', () => {
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z"/></svg>';
            });

            playBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                console.log('[AudioPlayer] play/pause clicado');
                ws.playPause();
            });

            if (rateBtn) {
                rateBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    speedIdx = (speedIdx + 1) % SPEEDS.length;
                    const r = SPEEDS[speedIdx];
                    ws.setPlaybackRate(r);
                    rateBtn.textContent = r + '×';
                });
            }

            // Carrega o audio via load() em vez de url na config
            console.log('[AudioPlayer] carregando audio...');
            try {
                ws.load(src);
            } catch (e) {
                console.error('[AudioPlayer] erro no load()', e);
                this.fallbackAudio(wrap, src, out);
            }
        },

        fallbackAudio(wrap, src, out) {
            console.log('[AudioPlayer] usando fallback nativo');
            // Limpa o conteudo atual
            wrap.innerHTML = '';
            wrap.className = wrap.className.replace('rounded-full border px-2 py-1', '').trim();
            
            // Cria audio nativo
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.preload = 'metadata';
            audio.src = src;
            audio.className = 'h-8 w-full';
            audio.style.maxWidth = '220px';
            
            wrap.appendChild(audio);
            wrap.style.maxWidth = '240px';
            wrap.style.minWidth = '180px';
        },

        destroyIn(root) {
            const el = root || document;
            el.querySelectorAll('[data-inbox-audio-mounted]').forEach((wrap) => {
                const ws = instances.get(wrap);
                if (ws) {
                    try {
                        ws.destroy();
                    } catch (e) {
                        /* ignore */
                    }
                    instances.delete(wrap);
                }
                wrap.removeAttribute('data-inbox-audio-mounted');
            });
        },
    };
})(window);
