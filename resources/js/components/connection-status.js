/**
 * Pastille d'état de la liaison Meta, dans l'en-tête. Se rafraîchit seule
 * toutes les minutes ; le détail et les corrections sont dans le panneau.
 */
export default () => ({
    open: false,
    status: null,
    checks: [],
    checkedAt: null,

    init() {
        this.load();
        setInterval(() => this.load(), 60000);
    },

    async load(fresh = false) {
        try {
            const response = await fetch(`/connection-status${fresh ? '?fresh=1' : ''}`, {
                headers: { Accept: 'application/json' },
            });

            const payload = await response.json();

            this.status = payload.status;
            this.checks = payload.checks ?? [];
            this.checkedAt = payload.checked_at;
        } catch (exception) {
            this.status = 'error';
            this.checkedAt = null;
            this.checks = [{
                label: 'Console',
                status: 'error',
                detail: "L'état de la liaison n'a pas pu être récupéré.",
                advice: 'Vérifiez que la console est toujours connectée au serveur.',
            }];
        }
    },

    get label() {
        if (this.status === null) {
            return 'Vérification…';
        }

        return this.status === 'ok' ? 'WhatsApp connecté' : 'WhatsApp : problème';
    },

    get pillClass() {
        if (this.status === null) {
            return 'border-line text-ink-muted';
        }

        return this.status === 'ok'
            ? 'border-line text-ink-muted'
            : 'border-danger bg-danger-soft text-danger-strong';
    },

    dotClass(status) {
        if (status === null || status === undefined) {
            return 'bg-line';
        }

        return status === 'ok' ? 'bg-read' : 'bg-danger';
    },
});
