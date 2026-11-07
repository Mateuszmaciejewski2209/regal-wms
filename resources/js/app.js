import './echo';

/*
 * Globalne zachowania panelu (Alpine jest dostarczany przez Livewire):
 *  - toasty (z Livewire przez zdarzenie przeglądarki „toast” i ze skanów na żywo),
 *  - stan połączenia WebSocket,
 *  - nasłuch skanów z telefonu użytkownika (kanał prywatny scanner.{id}).
 */

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('toasts', {
        items: [],
        nextId: 1,
        push({ message, tone = 'neutral', title = null, timeout = 4200 }) {
            const id = this.nextId++;
            this.items.push({ id, message, tone, title });
            if (this.items.length > 4) this.items.shift();
            if (timeout) setTimeout(() => this.dismiss(id), timeout);
        },
        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    Alpine.store('live', { state: 'connecting' });

    // Okno potwierdzenia zamiast natywnego confirm(): $confirm({...}).then(ok => …)
    Alpine.store('confirm', {
        open: false,
        title: '',
        message: '',
        confirm: 'Potwierdź',
        cancel: 'Anuluj',
        danger: true,
        resolver: null,
        ask(options = {}) {
            Object.assign(this, { title: 'Na pewno?', message: '', confirm: 'Potwierdź', cancel: 'Anuluj', danger: true }, options, { open: true });
            return new Promise((resolve) => (this.resolver = resolve));
        },
        answer(value) {
            this.open = false;
            this.resolver?.(value);
            this.resolver = null;
        },
    });
    Alpine.magic('confirm', () => (options) => Alpine.store('confirm').ask(options));

    // Plan magazynu: przeciąganie regałów po siatce (przyciąganie do kratek), zapis po upuszczeniu.
    // Plan hali: przeciąganie regałów po siatce. Granice planu i kolizje sprawdzane już w trakcie
    // przeciągania (czerwona ramka), a po upuszczeniu regał od razu zostaje w nowym miejscu –
    // serwer tylko to zapisuje, więc nic nie „przeskakuje”.
    Alpine.data('rackPlan', (cell, planWidth, planHeight) => ({
        cell,
        drag: null,
        rect(el) {
            const [x, y, w, h] = el.dataset.rect.split(',').map(Number);
            return { x, y, w, h };
        },
        down(event, id) {
            if (event.button !== 0) return;
            const el = event.currentTarget;
            el.setPointerCapture(event.pointerId);
            const others = [...this.$root.querySelectorAll('[data-rect]')].filter((other) => other !== el).map((other) => this.rect(other));
            this.drag = { id, el, ...this.rect(el), others, sx: event.clientX, sy: event.clientY, dx: 0, dy: 0, blocked: false };
        },
        move(event) {
            const d = this.drag;
            if (!d) return;
            const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
            d.dx = clamp(Math.round((event.clientX - d.sx) / this.cell), -d.x, planWidth - d.w - d.x);
            d.dy = clamp(Math.round((event.clientY - d.sy) / this.cell), -d.y, planHeight - d.h - d.y);
            const nx = d.x + d.dx;
            const ny = d.y + d.dy;
            d.blocked = d.others.some((o) => nx < o.x + o.w && o.x < nx + d.w && ny < o.y + o.h && o.y < ny + d.h);
            d.el.style.transform = `translate(${d.dx * this.cell}px, ${d.dy * this.cell}px)`;
            d.el.style.zIndex = 10;
            d.el.classList.toggle('is-blocked', d.blocked);
        },
        up() {
            const d = this.drag;
            if (!d) return;
            this.drag = null;
            d.el.classList.remove('is-blocked');
            d.el.style.zIndex = '';

            if (!d.dx && !d.dy) {
                d.el.style.transform = '';
                this.$wire.selectRack(d.id);
                return;
            }

            if (d.blocked) {
                d.el.style.transform = ''; // płynny powrót na stare miejsce
                return;
            }

            // Nowa pozycja od razu w DOM, bez animacji powrotu transform → 0.
            d.el.style.transition = 'none';
            d.el.style.left = `${(d.x + d.dx) * this.cell + 1}px`;
            d.el.style.top = `${(d.y + d.dy) * this.cell + 1}px`;
            d.el.style.transform = '';
            d.el.dataset.rect = [d.x + d.dx, d.y + d.dy, d.w, d.h].join(',');
            void d.el.offsetWidth;
            d.el.style.transition = '';
            this.$wire.moveRack(d.id, d.x + d.dx, d.y + d.dy);
        },
        cancel() {
            if (this.drag) {
                this.drag.el.style.transform = '';
                this.drag.el.classList.remove('is-blocked');
            }
            this.drag = null;
        },
    }));

    // Lista wyboru (komponent <x-ui.select>), wartość synchronizowana z Livewire przez x-modelable.
    Alpine.data('selectBox', (items, nullable = false) => ({
        items,
        nullable,
        value: null,
        open: false,
        query: '',
        active: 0,
        get selected() {
            return this.items.find((item) => this.isSelected(item)) ?? null;
        },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            return q ? this.items.filter((item) => item.label.toLowerCase().includes(q)) : this.items;
        },
        isSelected(item) {
            const current = this.value === null || this.value === undefined ? '' : String(this.value);
            return item.value === current;
        },
        toggle() {
            this.open ? this.close() : this.openList();
        },
        openList() {
            this.open = true;
            this.query = '';
            this.active = Math.max(0, this.filtered.findIndex((item) => this.isSelected(item)));
            this.$nextTick(() => {
                (this.$refs.search ?? this.$refs.list).focus();
                this.scrollToActive();
            });
        },
        close(focusButton = false) {
            if (!this.open) return;
            this.open = false;
            if (focusButton) this.$refs.button.focus();
        },
        move(delta) {
            const count = this.filtered.length;
            if (!count) return;
            this.active = (this.active + delta + count) % count;
            this.scrollToActive();
        },
        scrollToActive() {
            this.$refs.list.querySelectorAll('[role=option]')[this.active]?.scrollIntoView({ block: 'nearest' });
        },
        chooseActive() {
            const item = this.filtered[this.active];
            if (item) this.choose(item);
        },
        choose(item) {
            this.value = item.value === '' && this.nullable ? null : item.value;
            this.close(true);
        },
    }));

    Alpine.store('theme', {
        value: localStorage.getItem('theme') || 'system',
        set(value) {
            this.value = value;
            localStorage.setItem('theme', value);
            applyTheme(value);
        },
        toggle() {
            const isDark = document.documentElement.dataset.theme === 'dark';
            this.set(isDark ? 'light' : 'dark');
        },
    });

    // Skany z telefonu – toast w całym panelu, niezależnie od otwartej strony.
    Alpine.data('liveScanner', (userId) => ({
        init() {
            // wire:navigate inicjuje <body> ponownie – subskrybujemy kanał tylko raz.
            if (!window.Echo || !userId || window.__scannerListening) return;
            window.__scannerListening = true;
            window.Echo.private(`scanner.${userId}`)
                .listen('.scan.received', (scan) => {
                    if (scan.kind !== 'scan') return;
                    const tone = { added: 'success', found: 'neutral', not_found: 'danger', rejected: 'warn' }[scan.result] ?? 'neutral';
                    Alpine.store('toasts').push({
                        title: scan.product?.name ?? scan.code,
                        message: scan.document ? `${scan.document.number} · ${scan.message}` : scan.message,
                        tone,
                    });
                })
                .listen('.scanner.paired', (device) => {
                    Alpine.store('toasts').push({ title: 'Sparowano telefon', message: device.name, tone: 'success' });
                });
        },
    }));
});

window.addEventListener('toast', (event) => {
    window.Alpine?.store('toasts').push(event.detail ?? {});
});

function applyTheme(value) {
    const dark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    applyTheme(localStorage.getItem('theme') || 'system');
});

// Stan połączenia z serwerem WebSocket (Reverb).
const connection = window.Echo?.connector?.pusher?.connection;
connection?.bind('state_change', ({ current }) => {
    const store = window.Alpine?.store('live');
    if (store) store.state = current;
});
document.addEventListener('alpine:initialized', () => {
    if (connection) window.Alpine.store('live').state = connection.state;
});
