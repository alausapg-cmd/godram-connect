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

// The CBT examination screen. The server keeps the clock and the marks; this keeps
// answers safe on the phone until the server confirms them, and retries when the
// connection comes back. Every save carries a revision number so an old save can
// never overwrite a newer answer.
Alpine.data('cbt', (config) => ({
    questions: config.questions,
    i: 0,
    offset: config.now - Date.now(),
    deadline: config.deadline,
    left: Math.max(0, Math.floor((config.deadline - config.now) / 1000)),
    online: navigator.onLine,
    queue: {},
    sending: false,
    state: 'saved',
    submitting: false,
    confirming: false,
    palette: false,
    finished: false,
    notice: '',
    warned: Object.fromEntries([10, 5, 1].filter((m) => (config.deadline - config.now) / 1000 <= m * 60 + 5).map((m) => [m, true])),
    shownAt: Date.now(),
    flushTimer: null,
    key: 'godram-cbt-' + config.attempt,

    init() {
        // Answers typed while offline survive a reload or a closed tab.
        try {
            const saved = JSON.parse(localStorage.getItem(this.key) || '{}');
            Object.values(saved).forEach((entry) => {
                const q = this.questions.find((x) => x.position === entry.position);
                if (q && entry.revision > q.revision) {
                    q.response = entry.response;
                    q.flagged = entry.flagged;
                    q.revision = entry.revision;
                    this.queue[q.position] = entry;
                }
            });
        } catch (e) { /* storage unavailable */ }
        const first = this.questions.findIndex((q) => !this.isAnswered(q));
        this.i = first > 0 ? first : 0;

        setInterval(() => this.tick(), 1000);
        setInterval(() => this.flush(), 5000);
        setInterval(() => this.sync(), 30000);
        window.addEventListener('online', () => { this.online = true; this.signal('online'); this.flush(); this.sync(); });
        window.addEventListener('offline', () => { this.online = false; this.state = 'offline'; this.signal('offline'); });
        document.addEventListener('visibilitychange', () => { if (document.hidden && !this.finished) this.signal('focus_lost'); });
        window.addEventListener('beforeunload', (e) => { if (Object.keys(this.queue).length && !this.finished) e.preventDefault(); });
        document.addEventListener('keydown', (e) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || this.confirming) return;
            if (e.key === 'ArrowRight') this.go(this.i + 1);
            if (e.key === 'ArrowLeft') this.go(this.i - 1);
        });
        this.signal('presented', { position: this.q.position });
        if (Object.keys(this.queue).length) this.flush();
    },

    get q() { return this.questions[this.i]; },
    get total() { return this.questions.length; },
    get answeredCount() { return this.questions.filter((q) => this.isAnswered(q)).length; },
    get flaggedCount() { return this.questions.filter((q) => q.flagged).length; },
    get unansweredCount() { return this.total - this.answeredCount; },
    get clock() {
        const h = Math.floor(this.left / 3600), m = Math.floor((this.left % 3600) / 60), s = this.left % 60;
        return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    },

    isAnswered(q) {
        const r = q.response;
        if (r === null || r === undefined || r === '') return false;
        if (Array.isArray(r)) return r.length > 0;
        if (typeof r === 'object') return Object.values(r).some((v) => v !== null && v !== '');
        return true;
    },

    tick() {
        this.left = Math.max(0, Math.floor((this.deadline - (Date.now() + this.offset)) / 1000));
        for (const [mins, text] of [[10, '10 minutes left.'], [5, '5 minutes left. Check your flagged questions.'], [1, 'One minute left. Your answers are being saved.']]) {
            if (this.left <= mins * 60 && this.left > (mins * 60) - 5 && !this.warned[mins]) {
                this.warned[mins] = true;
                this.notice = text;
                setTimeout(() => { if (this.notice === text) this.notice = ''; }, 8000);
            }
        }
        if (this.left <= 0 && !this.finished) this.submit(true);
    },

    go(n) {
        if (n < 0 || n >= this.total || n === this.i) return;
        this.spent();
        this.i = n;
        this.palette = false;
        this.signal('presented', { position: this.q.position });
        this.$nextTick(() => document.getElementById('question-top')?.focus({ preventScroll: false }));
    },

    // Time spent on a question travels with its next save.
    spent() {
        const q = this.q;
        q._seconds = (q._seconds || 0) + Math.round((Date.now() - this.shownAt) / 1000);
        this.shownAt = Date.now();
    },

    set(value) { this.q.response = value; this.enqueue(this.q); },
    toggle(key) {
        const now = Array.isArray(this.q.response) ? [...this.q.response] : [];
        const at = now.indexOf(key);
        at === -1 ? now.push(key) : now.splice(at, 1);
        this.set(now);
    },
    order() { return this.q.response || this.q.options.map((o) => o.key); },
    move(index, step) {
        const keys = [...this.order()];
        const to = index + step;
        if (to < 0 || to >= keys.length) return;
        [keys[index], keys[to]] = [keys[to], keys[index]];
        this.set(keys);
    },
    optionText(key) { return (this.q.options.find((o) => o.key === key) || {}).text; },
    match(left, right) { this.set({ ...(this.q.response || {}), [left]: right }); },
    clear() { this.set(null); },
    flag() { this.q.flagged = !this.q.flagged; this.enqueue(this.q); },

    enqueue(q) {
        q.revision += 1;
        if (q === this.q) this.spent();
        this.queue[q.position] = { position: q.position, revision: q.revision, response: q.response, flagged: q.flagged, seconds: q._seconds || 0 };
        q._seconds = 0;
        this.persist();
        this.state = this.online ? 'saving' : 'offline';
        clearTimeout(this.flushTimer);
        this.flushTimer = setTimeout(() => this.flush(), 700);
    },
    persist() { try { localStorage.setItem(this.key, JSON.stringify(this.queue)); } catch (e) { /* full or blocked */ } },

    async request(url, body, method = 'POST') {
        const res = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
        });
        let json = {};
        try { json = await res.json(); } catch (e) { /* empty */ }
        return { status: res.status, json };
    },

    async flush() {
        if (this.sending || this.finished) return;
        const pending = Object.values(this.queue);
        if (!pending.length) { if (this.online) this.state = 'saved'; return; }
        if (!navigator.onLine) { this.state = 'offline'; return; }
        this.sending = true;
        this.state = 'saving';
        try {
            for (const entry of pending) {
                const { status, json } = await this.request(config.urls.save, entry);
                if (status === 409) { this.done(json.result_url); return; }
                if (status === 422) { this.notice = json.message || 'That answer could not be saved.'; }
                else if (status >= 400) throw new Error(status);
                if (json.now) this.offset = json.now - Date.now();
                if (json.deadline) this.deadline = json.deadline;
                if (this.queue[entry.position]?.revision === entry.revision) delete this.queue[entry.position];
            }
            this.persist();
            this.online = true;
            this.state = Object.keys(this.queue).length ? 'saving' : 'saved';
        } catch (e) {
            this.state = navigator.onLine ? 'retrying' : 'offline';
        } finally {
            this.sending = false;
        }
    },

    async sync() {
        if (!navigator.onLine || this.finished) return;
        try {
            const res = await fetch(config.urls.state, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            this.offset = json.now - Date.now();
            this.deadline = json.deadline;
            this.online = true;
            if (json.status !== 'in_progress') this.done(json.result_url);
        } catch (e) { /* try again next time */ }
    },

    signal(type, data = {}) {
        if (!navigator.onLine && type !== 'online') return;
        this.request(config.urls.signal, { type, ...data }).catch(() => {});
    },

    async submit(auto = false) {
        if (this.submitting) return;
        this.submitting = true;
        this.confirming = false;
        this.spent();
        if (this.q.revision && this.q._seconds) this.enqueue(this.q);
        await this.flush();
        try {
            const { status, json } = await this.request(config.urls.submit, { auto });
            if (status >= 400 && status !== 409) throw new Error(status);
            this.done(json.result_url || config.urls.result);
        } catch (e) {
            this.submitting = false;
            this.state = 'offline';
            this.notice = auto
                ? 'Time is up. We could not reach the server, but every answer it already received will be marked. Keep this page open to send the rest.'
                : 'We could not reach the server. Your answers are kept on this phone. Try again when you are back online.';
            if (auto) setTimeout(() => { this.submitting = false; this.submit(true); }, 10000);
        }
    },

    done(url) {
        this.finished = true;
        try { localStorage.removeItem(this.key); } catch (e) { /* ignore */ }
        window.location.href = url || config.urls.result;
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
