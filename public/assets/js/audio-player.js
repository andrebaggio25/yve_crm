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
            if (!src || typeof global.WaveSurfer === 'undefined') {
                console.warn('[AudioPlayer] sem src ou WaveSurfer');
                return;
            }
            const out = wrap.classList.contains('inbox-audio-out');
            const wfEl = wrap.querySelector('[data-waveform]');
            const playBtn = wrap.querySelector('[data-play]');
            const timeEl = wrap.querySelector('[data-time]');
            const rateBtn = wrap.querySelector('[data-rate]');
            if (!wfEl || !playBtn) {
                console.warn('[AudioPlayer] elementos nao encontrados');
                return;
            }

            const waveColor = out ? 'rgba(255,255,255,0.35)' : 'rgba(15,23,42,0.22)';
            const progColor = out ? '#ffffff' : '#0d9488';

            let speedIdx = 0;
            let ws;
            try {
                ws = global.WaveSurfer.create({
                    container: wfEl,
                    waveColor: waveColor,
                    progressColor: progColor,
                    cursorColor: progColor,
                    barWidth: 2,
                    barRadius: 2,
                    height: 32,
                    normalize: true,
                    url: src,
                });
                instances.set(wrap, ws);
            } catch (e) {
                console.error('[AudioPlayer] erro ao criar WaveSurfer', e);
                this.fallbackAudio(wrap, src, out);
                return;
            }

            ws.on('ready', () => {
                if (timeEl) {
                    timeEl.textContent = formatTime(ws.getDuration());
                }
            });
            ws.on('error', (err) => {
                console.error('[AudioPlayer] erro ao carregar audio', src, err);
                this.fallbackAudio(wrap, src, out);
            });
            ws.on('audioprocess', () => {
                if (timeEl) {
                    timeEl.textContent = formatTime(ws.getCurrentTime()) + ' / ' + formatTime(ws.getDuration());
                }
            });
            ws.on('play', () => {
                playBtn.textContent = '❚❚';
            });
            ws.on('pause', () => {
                playBtn.textContent = '▶';
            });
            ws.on('finish', () => {
                playBtn.textContent = '▶';
            });

            playBtn.addEventListener('click', () => {
                ws.playPause();
            });

            if (rateBtn) {
                rateBtn.addEventListener('click', () => {
                    speedIdx = (speedIdx + 1) % SPEEDS.length;
                    const r = SPEEDS[speedIdx];
                    ws.setPlaybackRate(r);
                    rateBtn.textContent = r + '×';
                });
            }
        },

        fallbackAudio(wrap, src, out) {
            // Se WaveSurfer falhar, substitui por audio nativo
            wrap.innerHTML = `<audio controls class="h-8 w-full" preload="metadata" src="${src}"></audio>`;
            wrap.style.maxWidth = '260px';
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
