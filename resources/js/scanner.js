/*
 * Regał – mobilny skaner (PWA).
 *
 * Telefon zachowuje się jak bezprzewodowy czytnik kodów:
 *  1. parowanie jednorazowym kodem z panelu → token urządzenia (localStorage),
 *  2. kamera odczytuje QR etykiety (RGL:<indeks>) lub kod kreskowy EAN/Code128,
 *  3. każdy odczyt = webhook POST /api/scanner/scans (Bearer token),
 *  4. serwer dopisuje pozycję i rozgłasza zdarzenie przez WebSocket do panelu.
 * Brak sieci → skany trafiają do kolejki i są wysyłane po odzyskaniu połączenia.
 */
import Alpine from 'alpinejs';
import { Html5Qrcode, Html5QrcodeSupportedFormats as F } from 'html5-qrcode';

const STORAGE = {
    token: 'regal.scanner.token',
    device: 'regal.scanner.device',
    queue: 'regal.scanner.queue',
    history: 'regal.scanner.history',
    settings: 'regal.scanner.settings',
    location: 'regal.scanner.location',
};

const TRIGGER_WINDOW_MS = 2500; // po naciśnięciu „Skanuj” – czas na odczyt kodu z ramki
const RESULT_HIDE_MS = 4000; // udany skan w trybie „Dodaj” lub „Rozmieść”
const RESULT_HIDE_LONG_MS = 8000; // „Sprawdź” i błędy – więcej czasu na przeczytanie

const read = (key, fallback) => {
    try {
        const raw = localStorage.getItem(key);
        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
};
const write = (key, value) => localStorage.setItem(key, JSON.stringify(value));

class ApiError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

window.scannerApp = () => ({
    screen: read(STORAGE.token, null) ? 'scan' : 'pair',
    token: read(STORAGE.token, null),
    device: read(STORAGE.device, null),

    // parowanie
    pairCode: '',
    deviceName: guessDeviceName(),
    pairing: false,
    pairError: null,

    // skanowanie
    camera: 'idle', // idle | starting | running | denied | unsupported | error
    cameraMessage: '',
    mode: read(STORAGE.settings, {}).mode ?? 'add',
    quantity: 1,
    location: read(STORAGE.location, null), // półka, na którą odkładamy w trybie „Rozmieść”
    fill: read(STORAGE.settings, {}).fill ?? 25,
    target: null,
    result: null,
    flash: null,
    busy: false,
    torchSupported: false,
    torchOn: false,
    online: navigator.onLine,
    queue: read(STORAGE.queue, []),
    history: read(STORAGE.history, []),
    sheet: null, // targets | manual | history | menu
    documents: [],
    loadingDocuments: false,
    manualCode: '',
    secure: window.isSecureContext,

    reader: null,
    armed: false, // „Skanuj” naciśnięte – przyjmujemy pierwszy kod z ramki
    armTimer: null,
    missed: false,
    resultTimer: null,
    audio: null,
    wakeLock: null,
    heartbeat: null,

    init() {
        const hash = new URLSearchParams(window.location.hash.slice(1));
        if (hash.get('pair')) {
            this.pairCode = hash.get('pair');
            history.replaceState(null, '', window.location.pathname);
            if (!this.token) this.$nextTick(() => this.$refs.deviceName?.focus());
        }

        window.addEventListener('online', () => {
            this.online = true;
            this.flushQueue();
        });
        window.addEventListener('offline', () => (this.online = false));
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.screen === 'scan') {
                this.requestWakeLock();
                this.refreshMe();
            }
        });

        // Karta wyniku znika sama: szybko po udanym skanie, wolniej przy podglądzie i błędach.
        this.$watch('result', (result) => {
            clearTimeout(this.resultTimer);
            if (!result) return;
            const quick = this.resultTone === 'ok' && this.mode !== 'lookup';
            this.resultTimer = setTimeout(() => (this.result = null), quick ? RESULT_HIDE_MS : RESULT_HIDE_LONG_MS);
        });

        if (this.screen === 'scan') this.enterScanScreen();

        if ('serviceWorker' in navigator && window.isSecureContext) {
            navigator.serviceWorker.register('/skaner/sw.js', { scope: '/skaner' }).catch(() => {});
        }
    },

    // ───────────────────────── API ─────────────────────────

    async api(method, url, body) {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 401 && this.token) {
            this.forgetDevice('Telefon został odłączony w panelu. Sparuj go ponownie.');
        }
        if (!response.ok) {
            throw new ApiError(response.status, data.message ?? `Błąd serwera (${response.status})`);
        }
        return data;
    },

    // ───────────────────────── Parowanie ─────────────────────────

    async pair() {
        const code = this.pairCode.trim().toUpperCase();
        if (!code) {
            this.pairError = 'Wpisz kod parowania widoczny w panelu.';
            return;
        }
        this.pairing = true;
        this.pairError = null;
        try {
            const data = await this.api('POST', '/api/scanner/pair', { code, name: this.deviceName.trim() || 'Telefon' });
            this.token = data.token;
            this.device = { ...data.device, user: data.user.name };
            write(STORAGE.token, this.token);
            write(STORAGE.device, this.device);
            await this.stopCamera();
            this.unlockAudio();
            this.feedback('success');
            this.screen = 'scan';
            this.enterScanScreen();
        } catch (e) {
            this.pairError = e.message;
            this.feedback('error');
        } finally {
            this.pairing = false;
        }
    },

    async scanPairingCode() {
        this.unlockAudio();
        await this.startCamera((text) => {
            const match = text.match(/pair=([A-Z0-9-]+)/i) ?? text.match(/^([A-Z0-9]{4}-?[A-Z0-9]{4})$/i);
            if (match) {
                this.pairCode = match[1].toUpperCase();
                this.stopCamera();
                this.pair();
            }
        });
    },

    forgetDevice(message = null) {
        localStorage.removeItem(STORAGE.token);
        localStorage.removeItem(STORAGE.device);
        this.token = null;
        this.device = null;
        this.target = null;
        this.sheet = null;
        clearInterval(this.heartbeat);
        this.stopCamera();
        this.screen = 'pair';
        this.pairError = message;
    },

    async unpair() {
        try {
            await this.api('DELETE', '/api/scanner/session');
        } catch {
            /* token mógł już wygasnąć */
        }
        this.forgetDevice();
    },

    // ───────────────────────── Ekran skanowania ─────────────────────────

    enterScanScreen() {
        this.refreshMe();
        this.flushQueue();
        clearInterval(this.heartbeat);
        this.heartbeat = setInterval(() => this.refreshMe(), 15000);
        this.$nextTick(() => this.startCamera((text) => this.onDecoded(text)));
        this.requestWakeLock();
    },

    async refreshMe() {
        if (!this.token || !navigator.onLine) return;
        try {
            const me = await this.api('GET', '/api/scanner/me');
            this.target = me.target;
            this.device = { ...me.device, user: me.user.name };
            write(STORAGE.device, this.device);
        } catch {
            /* offline lub 401 – obsłużone w api() */
        }
    },

    async openTargets() {
        this.sheet = 'targets';
        this.loadingDocuments = true;
        try {
            this.documents = (await this.api('GET', '/api/scanner/documents')).data;
        } catch (e) {
            this.toastError(e.message);
        } finally {
            this.loadingDocuments = false;
        }
    },

    async chooseTarget(id) {
        try {
            const data = await this.api('POST', '/api/scanner/target', { document_id: id });
            this.target = data.target;
            if (id) this.setMode('add');
            this.sheet = null;
            this.feedback('success');
        } catch (e) {
            this.toastError(e.message);
        }
    },

    setMode(mode) {
        this.mode = mode;
        write(STORAGE.settings, { ...read(STORAGE.settings, {}), mode });
    },

    setFill(fill) {
        this.fill = fill;
        write(STORAGE.settings, { ...read(STORAGE.settings, {}), fill });
    },

    clearLocation() {
        this.location = null;
        localStorage.removeItem(STORAGE.location);
    },

    stepQuantity(delta) {
        const next = Math.round((Number(this.quantity) + delta) * 1000) / 1000;
        this.quantity = Math.max(1, next);
    },

    // ───────────────────────── Kamera ─────────────────────────

    async startCamera(onText) {
        if (!window.isSecureContext) {
            this.camera = 'unsupported';
            this.cameraMessage = 'Kamera wymaga połączenia HTTPS. Otwórz skaner przez tunel https:// lub użyj ręcznego wpisywania kodu.';
            return;
        }
        if (this.camera === 'running' || this.camera === 'starting') return;

        this.camera = 'starting';
        try {
            this.reader ??= new Html5Qrcode('reader', {
                verbose: false,
                formatsToSupport: [F.QR_CODE, F.EAN_13, F.EAN_8, F.CODE_128, F.CODE_39, F.UPC_A, F.UPC_E, F.DATA_MATRIX],
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            });

            await this.reader.start(
                { facingMode: 'environment' },
                { fps: 12, disableFlip: true, qrbox: (w, h) => this.scanRegion(w, h) },
                (text) => onText(text),
                () => {},
            );

            this.camera = 'running';
            try {
                this.torchSupported = this.reader.getRunningTrackCameraCapabilities().torchFeature().isSupported();
            } catch {
                this.torchSupported = false;
            }
        } catch (e) {
            const denied = /NotAllowed|Permission/i.test(String(e?.name ?? e));
            this.camera = denied ? 'denied' : 'error';
            this.cameraMessage = denied
                ? 'Brak zgody na kamerę. Zezwól na dostęp w ustawieniach przeglądarki i spróbuj ponownie.'
                : 'Nie udało się uruchomić kamery. Spróbuj ponownie lub wpisz kod ręcznie.';
        }
    },

    async stopCamera() {
        if (this.reader && this.camera === 'running') {
            try {
                await this.reader.stop();
            } catch {
                /* już zatrzymana */
            }
        }
        this.camera = 'idle';
        this.torchOn = false;
        this.armed = false;
    },

    async toggleTorch() {
        try {
            this.torchOn = !this.torchOn;
            await this.reader.getRunningTrackCameraCapabilities().torchFeature().apply(this.torchOn);
        } catch {
            this.torchOn = false;
        }
    },

    async requestWakeLock() {
        try {
            this.wakeLock = await navigator.wakeLock?.request('screen');
        } catch {
            /* nieobsługiwane – ekran wygaśnie normalnie */
        }
    },

    // ───────────────────────── Skan ─────────────────────────

    /** Spust jak w czytniku ręcznym: kamera działa cały czas, ale kod przyjmujemy dopiero po naciśnięciu „Skanuj”. */
    trigger() {
        if (this.camera !== 'running' || this.sheet) return;
        this.unlockAudio();
        this.result = null;
        this.missed = false;
        this.armed = true;
        clearTimeout(this.armTimer);
        this.armTimer = setTimeout(() => {
            if (!this.armed) return;
            this.armed = false;
            this.missed = true;
            setTimeout(() => (this.missed = false), 2500);
        }, TRIGGER_WINDOW_MS);
    },

    onDecoded(raw) {
        const code = String(raw).trim();
        // Jedno naciśnięcie = jeden skan; kody poza ramką biblioteka w ogóle pomija (qrbox).
        if (!code || !this.armed || this.sheet) return;

        this.armed = false;
        clearTimeout(this.armTimer);
        this.submit(code);
    },

    dismissResult() {
        this.result = null;
    },

    /**
     * Obszar odczytu = ramka celownika na ekranie. Wideo ma object-fit: cover, a biblioteka przelicza obszar tak,
     * jakby obraz był rozciągnięty na cały element, więc korygujemy wymiary o skalę przycięcia.
     */
    scanRegion(viewWidth, viewHeight) {
        const box = document.querySelector('.reticle')?.getBoundingClientRect();
        const video = document.querySelector('#reader video');
        let width = box?.width || viewWidth * 0.72;
        let height = box?.height || width / 1.45;

        if (video?.videoWidth && video?.videoHeight) {
            const scale = Math.max(viewWidth / video.videoWidth, viewHeight / video.videoHeight);
            width = (width / scale) * (viewWidth / video.videoWidth);
            height = (height / scale) * (viewHeight / video.videoHeight);
        }

        return {
            width: Math.round(Math.min(viewWidth, Math.max(50, width))),
            height: Math.round(Math.min(viewHeight, Math.max(50, height))),
        };
    },

    submitManual() {
        const code = this.manualCode.trim();
        if (!code) return;
        this.manualCode = '';
        this.sheet = null;
        this.unlockAudio();
        this.submit(code);
    },

    async submit(code) {
        const payload = { code, quantity: String(this.quantity), mode: this.mode };
        // Dokument i półka z chwili skanu – skan z kolejki offline nie trafi później w inne miejsce.
        if (this.mode === 'add' && this.target) payload.document_id = this.target.id;
        if (this.mode === 'putaway') {
            if (this.location) payload.location = this.location.qr;
            payload.fill = this.fill;
        }

        if (!navigator.onLine) {
            this.enqueue(payload);
            return;
        }

        this.busy = true;
        try {
            const result = await this.api('POST', '/api/scanner/scans', payload);
            this.showResult(result);
            if (this.mode === 'add' && result.result === 'added') this.quantity = 1;
        } catch (e) {
            if (e instanceof ApiError) {
                this.toastError(e.message);
            } else {
                this.enqueue(payload); // błąd sieci – kolejka offline
            }
        } finally {
            this.busy = false;
        }
    },

    showResult(result) {
        this.result = result;
        if (result.document) this.target = { ...(this.target ?? {}), ...result.document };

        // Skan etykiety półki w trybie „Rozmieść” ustawia półkę docelową; odłożenie odświeża jej zawartość.
        if (result.location && (result.result === 'placed' || (result.result === 'location' && this.mode === 'putaway'))) {
            this.location = result.location;
            write(STORAGE.location, this.location);
        }

        const ok = ['added', 'found', 'location', 'placed'].includes(result.result);
        this.feedback(ok ? 'success' : 'error');

        if (result.result === 'no_target') {
            setTimeout(() => this.openTargets(), 700);
        }

        this.history.unshift({
            id: result.id,
            code: result.code,
            name: result.product?.name ?? 'Nieznany kod',
            result: result.result,
            message: result.message,
            at: new Date().toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        });
        this.history = this.history.slice(0, 30);
        write(STORAGE.history, this.history);
    },

    toastError(message) {
        this.result = { result: 'rejected', message, product: null, code: '' };
        this.feedback('error');
    },

    // ───────────────────────── Kolejka offline ─────────────────────────

    enqueue(payload) {
        this.queue.push({ ...payload, at: Date.now() });
        write(STORAGE.queue, this.queue);
        this.result = { result: 'queued', message: 'Brak sieci, skan zapisany, wyślę po odzyskaniu połączenia.', product: null, code: payload.code };
        this.feedback('success');
    },

    async flushQueue() {
        if (!this.queue.length || !navigator.onLine || !this.token) return;

        const pending = [...this.queue];
        for (const payload of pending) {
            try {
                const result = await this.api('POST', '/api/scanner/scans', payload);
                this.queue.shift();
                write(STORAGE.queue, this.queue);
                this.showResult(result);
            } catch (e) {
                if (e instanceof ApiError && e.status === 422) {
                    this.queue.shift(); // nieprawidłowy – nie ponawiamy
                    write(STORAGE.queue, this.queue);
                    continue;
                }
                break;
            }
        }
    },

    // ───────────────────────── Informacja zwrotna ─────────────────────────

    unlockAudio() {
        try {
            this.audio ??= new (window.AudioContext || window.webkitAudioContext)();
            if (this.audio.state === 'suspended') this.audio.resume();
        } catch {
            this.audio = null;
        }
    },

    feedback(kind) {
        this.flash = kind;
        setTimeout(() => (this.flash = null), 220);

        navigator.vibrate?.(kind === 'success' ? 35 : [70, 50, 70]);

        if (!this.audio) return;
        const osc = this.audio.createOscillator();
        const gain = this.audio.createGain();
        osc.type = 'square';
        osc.frequency.value = kind === 'success' ? 2400 : 320;
        gain.gain.setValueAtTime(0.06, this.audio.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, this.audio.currentTime + (kind === 'success' ? 0.09 : 0.25));
        osc.connect(gain).connect(this.audio.destination);
        osc.start();
        osc.stop(this.audio.currentTime + 0.3);
    },

    // ───────────────────────── Prezentacja ─────────────────────────

    get resultTone() {
        return { added: 'ok', placed: 'ok', found: 'info', location: 'info', queued: 'info', not_found: 'bad', rejected: 'warn', no_target: 'warn' }[this.result?.result] ?? 'info';
    },

    get resultLabel() {
        return {
            added: 'Dodano', placed: 'Odłożono', found: 'Znaleziono', location: 'Półka', queued: 'W kolejce',
            not_found: 'Nieznany kod', rejected: 'Odrzucono', no_target: 'Nie dodano',
        }[this.result?.result] ?? '';
    },
});

function guessDeviceName() {
    const ua = navigator.userAgent;
    const android = ua.match(/Android [\d.]+; ([^;)]+)/);
    if (android) return android[1].replace(/Build.*/, '').trim().slice(0, 40);
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    return 'Telefon';
}

window.Alpine = Alpine;
Alpine.start();
