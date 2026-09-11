/**
 * Ouverture d'une conversation avec un numéro qui n'a jamais écrit : la
 * console envoie un modèle approuvé, le destinataire n'a plus qu'à répondre.
 */
export default () => ({
    open: false,
    waId: '',
    busy: false,
    error: null,

    show() {
        this.open = true;
        this.error = null;
        this.$nextTick(() => this.$refs.waId?.focus());
    },

    async submit() {
        const waId = this.waId.replace(/[^0-9]/g, '');

        if (this.busy) {
            return;
        }

        if (waId.length < 8) {
            this.error = 'Saisissez le numéro au format international, sans le +.';

            return;
        }

        this.busy = true;
        this.error = null;

        try {
            const response = await fetch('/conversations/invite', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ wa_id: waId }),
            });

            const payload = await response.json();

            if (response.ok && payload.ok) {
                window.location = `/conversations/${payload.conversation_id}`;

                return;
            }

            // Le message d'erreur vient de Meta : il nomme la cause exacte,
            // le plus souvent un destinataire hors liste autorisée.
            this.error = payload.errors?.wa_id?.[0] ?? payload.message ?? "L'invitation a échoué.";
        } catch (exception) {
            this.error = "La console n'a pas pu joindre le serveur.";
        } finally {
            this.busy = false;
        }
    },
});
