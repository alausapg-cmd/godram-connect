import Alpine from 'alpinejs';

// Saves a form quietly in the background so a dropped connection never loses work.
Alpine.data('autosave', (url, intervalMs = 8000) => ({
    dirty: false,
    state: 'saved',
    savedAt: null,
    timer: null,
    init() {
        this.$el.addEventListener('input', () => { this.dirty = true; this.state = 'unsaved'; });
        this.$el.addEventListener('change', () => { this.dirty = true; this.state = 'unsaved'; });
        this.timer = setInterval(() => this.save(), intervalMs);
        window.addEventListener('online', () => this.save());
        window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); } });
        this.$el.addEventListener('submit', () => { this.dirty = false; });
    },
    async save() {
        if (!this.dirty) return;
        if (!navigator.onLine) { this.state = 'offline'; return; }
        this.state = 'saving';
        const data = new FormData(this.$el);
        data.set('_method', 'PATCH');
        data.delete('action');
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            if (!res.ok) throw new Error(res.status);
            const json = await res.json();
            this.dirty = false;
            this.state = 'saved';
            this.savedAt = json.saved_at;
        } catch (e) {
            this.state = navigator.onLine ? 'error' : 'offline';
        }
    },
}));

// Filters a long checkbox list (e.g. report participants) as you type.
Alpine.data('filterList', () => ({
    term: '',
    matches(text) { return text.toLowerCase().includes(this.term.trim().toLowerCase()); },
}));

window.Alpine = Alpine;
Alpine.start();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// Offer installation at a calm moment rather than immediately.
let deferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    document.querySelectorAll('[data-install]').forEach((el) => el.classList.remove('hidden'));
});
document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-install]');
    if (!trigger || !deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    document.querySelectorAll('[data-install]').forEach((el) => el.classList.add('hidden'));
});
