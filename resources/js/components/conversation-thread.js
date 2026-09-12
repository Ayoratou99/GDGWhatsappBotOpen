const TWO_HOURS = 2 * 60 * 60;

/**
 * Fil ouvert : messages, décompte de la fenêtre de 24 h, envoi opérateur.
 * Le serveur fournit l'état initial, Echo fournit la suite.
 */
export default (conversationId, initialMessages = [], windowExpiresAt = null) => ({
    conversationId,
    messages: initialMessages,
    expiresAt: windowExpiresAt ? new Date(windowExpiresAt) : null,
    remaining: 0,
    draft: '',
    sending: false,
    error: null,
    confirmClear: false,
    clearing: false,
    typing: false,
    typingTimer: null,

    init() {
        this.tick();
        setInterval(() => this.tick(), 1000);
        this.$nextTick(() => this.scrollToBottom());

        window.Echo.private(`conversation.${this.conversationId}`)
            .listen('.message.received', (event) => {
                // Chaque entrant relance la fenêtre : le bandeau repart de 24 h.
                this.expiresAt = event.conversation.window_expires_at
                    ? new Date(event.conversation.window_expires_at)
                    : null;
                this.tick();
                this.push(event.message);
            })
            .listen('.bot.typing', () => this.showTyping())
            .listen('.message.sent', (event) => {
                this.typing = false;
                this.push(event.message);
            })
            .listen('.message.status', (event) => this.applyStatus(event));
    },

    /**
     * Couvre le temps de réflexion du bot. Le filet de sécurité évite qu'un
     * driver qui n'aboutit pas ne laisse l'indicateur affiché pour toujours.
     */
    showTyping() {
        this.typing = true;
        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => { this.typing = false; }, 20000);
        this.$nextTick(() => this.scrollToBottom());
    },

    tick() {
        this.remaining = this.expiresAt
            ? Math.max(0, Math.floor((this.expiresAt.getTime() - Date.now()) / 1000))
            : 0;
    },

    get open() {
        return this.remaining > 0;
    },

    get closing() {
        return this.open && this.remaining <= TWO_HOURS;
    },

    get bannerLabel() {
        if (!this.expiresAt) {
            return 'Aucun message entrant : aucune fenêtre ouverte';
        }

        if (!this.open) {
            return 'Fenêtre de 24 heures fermée';
        }

        return this.closing
            ? 'Fenêtre de 24 heures — fermeture imminente'
            : 'Fenêtre de 24 heures ouverte';
    },

    get countdown() {
        if (!this.expiresAt) {
            return '--:--:--';
        }

        const hours = Math.floor(this.remaining / 3600);
        const minutes = Math.floor((this.remaining % 3600) / 60);
        const seconds = this.remaining % 60;

        return [hours, minutes, seconds].map((value) => String(value).padStart(2, '0')).join(':');
    },

    get bannerClass() {
        if (!this.open) {
            return 'bg-danger-soft text-danger-strong';
        }

        return this.closing ? 'bg-warn-soft text-warn-strong' : 'bg-operator-soft text-operator-strong';
    },

    /**
     * Le même message peut arriver deux fois : une fois par la réponse à
     * l'envoi, une fois par le canal. On dédoublonne sur l'identifiant.
     */
    push(message) {
        if (this.messages.some((item) => item.id === message.id)) {
            return;
        }

        this.messages.push({ ...message, live: true });
        this.$nextTick(() => this.scrollToBottom());
    },

    applyStatus(event) {
        const message = this.messages.find((item) => item.id === event.id);

        if (message) {
            message.status = event.status;
            message.status_label = event.status_label;
            message.error_message = event.error_message;
        }
    },

    async send() {
        const body = this.draft.trim();

        if (!body || this.sending || !this.open) {
            return;
        }

        this.sending = true;
        this.error = null;

        try {
            const response = await fetch(`/conversations/${this.conversationId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ body }),
            });

            const payload = await response.json();

            if (!response.ok) {
                this.error = payload.message ?? "L'envoi a échoué.";

                return;
            }

            this.draft = '';
            this.push(payload.message);
        } catch (exception) {
            this.error = "L'envoi a échoué : la console n'a pas pu joindre le serveur.";
        } finally {
            this.sending = false;
        }
    },

    /**
     * Vide le fil affiché. Le contact et la fenêtre de 24 h sont conservés :
     * on efface l'historique, pas la relation.
     */
    async clear() {
        if (this.clearing) {
            return;
        }

        this.clearing = true;
        this.error = null;

        try {
            const response = await fetch(`/conversations/${this.conversationId}/messages`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (! response.ok) {
                this.error = "La conversation n'a pas pu être vidée.";

                return;
            }

            this.messages = [];
            this.confirmClear = false;
        } catch (exception) {
            this.error = "La console n'a pas pu joindre le serveur.";
        } finally {
            this.clearing = false;
        }
    },

    statusClass(message) {
        if (message.status === 'read') {
            return 'text-read';
        }

        return message.status === 'failed' ? 'text-danger-strong' : 'text-ink-muted';
    },

    scrollToBottom() {
        const thread = this.$refs.thread;

        if (thread) {
            thread.scrollTop = thread.scrollHeight;
        }
    },
});
