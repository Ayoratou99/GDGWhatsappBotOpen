/**
 * Liste de gauche. Écoute le canal « conversations » : tout message, entrant
 * comme sortant, remonte son fil en tête de liste.
 */
export default (initial = [], activeId = null) => ({
    conversations: initial,
    activeId,

    init() {
        window.Echo.private('conversations')
            .listen('.message.received', (event) => this.merge(event.conversation, true))
            .listen('.message.sent', (event) => this.merge(event.conversation, false));
    },

    merge(conversation, inbound) {
        // Le fil ouvert est lu en direct : son compteur reste à zéro.
        if (conversation.id === this.activeId) {
            conversation = { ...conversation, unread_count: 0 };
        } else if (!inbound) {
            const known = this.conversations.find((item) => item.id === conversation.id);
            conversation = { ...conversation, unread_count: known?.unread_count ?? 0 };
        }

        this.conversations = [
            conversation,
            ...this.conversations.filter((item) => item.id !== conversation.id),
        ];
    },

    href(conversation) {
        return `/conversations/${conversation.id}`;
    },

    preview(conversation) {
        if (!conversation.preview) {
            return 'Aucun message';
        }

        const prefix = conversation.author === 'contact' ? '' : 'Vous : ';

        return prefix + conversation.preview.replace(/\s+/g, ' ');
    },
});
