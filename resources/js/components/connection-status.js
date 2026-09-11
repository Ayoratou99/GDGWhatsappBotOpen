/**
 * État de la liaison Meta, dans l'en-tête. Détecte, explique, et répare les
 * deux pannes qui se corrigent par un appel à l'API : numéro non enregistré,
 * application non abonnée aux webhooks.
 */
export default () => ({
    open: false,
    status: null,
    checks: [],
    checkedAt: null,
    busy: false,
    pin: '',
    feedback: null,
    showHelp: false,

    init() {
        this.load();
        setInterval(() => this.load(), 60000);
    },

    async load(fresh = false) {
        try {
            const response = await fetch(`/connection-status${fresh ? '?fresh=1' : ''}`, {
                headers: { Accept: 'application/json' },
            });

            this.apply(await response.json());
        } catch (exception) {
            this.unreachable();
        }
    },

    async repair(action) {
        if (this.busy) {
            return;
        }

        if (action === 'register' && !/^\d{6}$/.test(this.pin)) {
            this.feedback = { ok: false, message: 'Le PIN doit comporter exactement six chiffres.' };

            return;
        }

        this.busy = true;
        this.feedback = null;

        try {
            const response = await fetch('/connection-status/repair', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ action, pin: this.pin || null }),
            });

            const payload = await response.json();

            this.feedback = {
                ok: response.ok && payload.ok === true,
                message: payload.message
                    ?? payload.errors?.pin?.[0]
                    ?? "L'opération a échoué.",
            };

            if (payload.state) {
                this.apply(payload.state);
            }

            if (this.feedback.ok) {
                this.pin = '';
            }
        } catch (exception) {
            this.feedback = { ok: false, message: "La console n'a pas pu joindre le serveur." };
        } finally {
            this.busy = false;
        }
    },

    apply(payload) {
        this.status = payload.status;
        this.checks = payload.checks ?? [];
        this.checkedAt = payload.checked_at;
    },

    unreachable() {
        this.status = 'error';
        this.checkedAt = null;
        this.checks = [{
            label: 'Console',
            status: 'error',
            detail: "L'état de la liaison n'a pas pu être récupéré.",
            advice: 'Vérifiez que la console est toujours connectée au serveur.',
            action: null,
            doc: null,
        }];
    },

    get label() {
        if (this.status === null) {
            return 'Vérification…';
        }

        return this.status === 'ok' ? 'WhatsApp connecté' : 'WhatsApp : action requise';
    },

    get pillClass() {
        return this.status === 'error'
            ? 'border-danger bg-danger-soft text-danger-strong'
            : 'border-line text-ink-muted';
    },

    dotClass(status) {
        if (status === 'ok') {
            return 'bg-read';
        }

        return status === 'error' ? 'bg-danger' : 'bg-line';
    },

    actionLabel(action) {
        return action === 'register' ? 'Enregistrer le numéro' : "Abonner l'application";
    },
});
