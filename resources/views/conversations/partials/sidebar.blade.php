@php($activeId = $conversation->id ?? null)

<aside class="flex w-80 shrink-0 flex-col border-r border-line bg-surface"
       x-data="conversationList(@js($conversations->map->toSidebarPayload()), @js($activeId))">

    <div class="flex h-14 items-center justify-between border-b border-line px-4">
        <h2 class="text-sm font-medium">Conversations</h2>

        <div x-data="inviteForm()">
            <button type="button" @click="show()" class="text-xs text-operator underline underline-offset-2">
                Inviter un numéro
            </button>

            <div x-show="open" x-cloak
                 @keydown.escape.window="open = false"
                 class="fixed inset-0 z-20 flex items-center justify-center bg-ink/40 px-4"
                 role="dialog" aria-modal="true" aria-label="Inviter un numéro à discuter">

                <div @click.outside="open = false" class="w-full max-w-md rounded-lg border border-line bg-surface p-5">
                    <h3 class="text-base font-medium">Inviter un numéro à discuter</h3>

                    <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                        Un numéro connecté à la Cloud API n'apparaît dans aucun carnet d'adresses : personne ne peut
                        vous écrire en premier. L'invitation envoie un message modèle qui ouvre le fil chez votre
                        destinataire ; sa réponse fera arriver la conversation ici et ouvrira la fenêtre de 24 heures.
                    </p>

                    <p class="mt-3 rounded border border-line bg-app px-3 py-2 text-xs leading-relaxed text-ink-muted">
                        Tant que l'application Meta est en mode Développement, seuls les numéros inscrits dans la liste
                        des destinataires autorisés — WhatsApp → API Setup → champ « To » — peuvent recevoir ce message.
                        Tout autre numéro sera refusé par Meta.
                    </p>

                    <label for="wa_id" class="mt-4 block text-sm font-medium">Numéro au format international</label>
                    <input id="wa_id" x-ref="waId" x-model="waId" type="text" inputmode="numeric"
                           placeholder="241770000000" @keydown.enter.prevent="submit()"
                           class="mt-1 w-full rounded border border-line px-3 py-2 font-mono text-[15px]">
                    <p class="mt-1 text-xs text-ink-muted">Indicatif pays compris, sans le « + » ni espaces.</p>

                    <p x-show="error" x-text="error" class="mt-3 text-sm text-danger-strong"></p>

                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="open = false" class="text-sm text-ink-muted underline underline-offset-2">
                            Annuler
                        </button>
                        <button type="button" @click="submit()" :disabled="busy"
                                class="rounded bg-operator px-4 py-2 text-sm font-medium text-white disabled:bg-line disabled:text-ink-muted"
                                x-text="busy ? 'Envoi…' : 'Envoyer l’invitation'"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <template x-for="item in conversations" :key="item.id">
            <a :href="href(item)"
               class="block border-b border-line px-4 py-3"
               :class="item.id === activeId ? 'bg-app' : ''">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="truncate text-sm font-medium" x-text="item.name"></span>
                    <span class="shrink-0 text-xs text-ink-muted" x-text="item.time"></span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="truncate text-sm text-ink-muted" x-text="preview(item)"></span>
                    <span x-show="item.unread_count > 0"
                          class="shrink-0 rounded-full bg-operator px-2 py-0.5 text-xs font-medium text-white"
                          x-text="item.unread_count"></span>
                </div>
            </a>
        </template>

        <p x-show="conversations.length === 0" class="px-4 py-6 text-sm text-ink-muted">
            Aucune conversation pour l'instant.
        </p>
    </div>
</aside>
