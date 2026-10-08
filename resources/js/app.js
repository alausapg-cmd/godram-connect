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


const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const postJson = async (url, data) => {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data),
    });
    if (!res.ok) throw new Error(res.status);
    return res.json();
};

// Call-and-response inside a lesson: answer, see if it was right, see the poll.
Alpine.data('callResponse', (url, mine = null) => ({
    answer: mine ?? '',
    sent: mine !== null,
    busy: false,
    result: null,
    error: null,
    async send() {
        if (!this.answer.trim()) { this.error = 'Write or choose an answer first.'; return; }
        this.busy = true; this.error = null;
        try {
            this.result = await postJson(url, { response: this.answer });
            this.sent = true;
        } catch (e) {
            this.error = navigator.onLine ? 'That did not go through. Try again.' : 'You are offline. Try again when you have a connection.';
        }
        this.busy = false;
    },
}));

// The live training room checks for new prompts and answers every few seconds.
// Plain polling works on shared hosting and on weak connections; it backs off when the tab is hidden.
Alpine.data('liveRoom', (feedUrl, askUrl, sessionId) => ({
    prompts: [],
    questions: [],
    joined: 0,
    live: false,
    drafts: {},
    question: '',
    asking: false,
    note: null,
    timer: null,
    init() {
        this.load();
        this.timer = setInterval(() => { if (!document.hidden) this.load(); }, 7000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.load(); });
    },
    async load() {
        try {
            const res = await fetch(feedUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            this.prompts = data.prompts; this.questions = data.questions; this.joined = data.joined; this.live = data.live;
        } catch (e) { /* stay quiet on a dropped connection; the next poll will catch up */ }
    },
    async respond(prompt, value) {
        const answer = value ?? this.drafts[prompt.id];
        if (!answer || !String(answer).trim()) return;
        try { await postJson(prompt.url, { response: answer }); prompt.mine = answer; this.load(); }
        catch (e) { this.note = 'Your answer did not go through. Try again.'; }
    },
    async ask() {
        if (!this.question.trim()) return;
        this.asking = true;
        try {
            await postJson(askUrl, { body: this.question, course_session_id: sessionId });
            this.question = ''; this.note = 'Sent to the facilitators.'; this.load();
        } catch (e) { this.note = 'Your question did not go through. Try again.'; }
        this.asking = false;
    },
    postJson,
    pct(tally, choice) {
        const total = Object.values(tally || {}).reduce((a, b) => a + b, 0);
        return total ? Math.round((tally[choice] / total) * 100) : 0;
    },
}));

// Audio lessons pick up where you left off, even after the page is closed.
Alpine.data('resumeAudio', (key) => ({
    init() {
        const audio = this.$el.querySelector('audio');
        const store = 'godram-audio-' + key;
        try {
            const saved = parseFloat(localStorage.getItem(store) || '0');
            if (saved > 5) audio.addEventListener('loadedmetadata', () => { audio.currentTime = saved; }, { once: true });
            audio.addEventListener('timeupdate', () => { if (Math.floor(audio.currentTime) % 5 === 0) localStorage.setItem(store, String(audio.currentTime)); });
            audio.addEventListener('ended', () => localStorage.removeItem(store));
        } catch (e) { /* private browsing */ }
    },
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
