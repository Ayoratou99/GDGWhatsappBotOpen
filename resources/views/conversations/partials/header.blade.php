<header class="flex h-14 shrink-0 items-center justify-between border-b border-line bg-surface px-6">
    <span class="font-medium">Console WhatsApp</span>

    <div class="flex items-center gap-4">
        <div class="relative" x-data="connectionStatus()">
            <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="dialog"
                    class="flex items-center gap-2 rounded-full border px-3 py-1 text-xs"
                    :class="pillClass">
                <span class="inline-block h-2 w-2 rounded-full" :class="dotClass(status)"></span>
                <span x-text="label"></span>
            </button>

            <div x-show="open" x-cloak
                 @click.outside="open = false"
                 @keydown.escape.window="open = false"
                 role="dialog" aria-label="État de la liaison WhatsApp"
                 class="absolute right-0 z-10 mt-2 w-[26rem] rounded-lg border border-line bg-surface text-left shadow-lg">

                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <span class="text-sm font-medium">Liaison avec Meta</span>
                    <span class="font-mono text-xs text-ink-muted">Phone ID {{ config('whatsapp.phone_id') ?: '—' }}</span>
                </div>

                <template x-for="check in checks" :key="check.label">
                    <div class="border-b border-line px-4 py-3">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="dotClass(check.status)"></span>
                            <span x-text="check.label"></span>
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-ink-muted" x-text="check.detail"></p>

                        <div x-show="check.advice" class="mt-2 rounded border border-danger bg-danger-soft px-3 py-2">
                            <p class="text-xs leading-relaxed text-danger-strong" x-text="check.advice"></p>

                            <div x-show="check.action === 'register'" class="mt-2 flex items-center gap-2">
                                <label :for="'pin-' + check.label" class="sr-only">PIN à six chiffres</label>
                                <input :id="'pin-' + check.label" x-model="pin" type="text" inputmode="numeric"
                                       maxlength="6" placeholder="------"
                                       class="w-24 rounded border border-line bg-surface px-2 py-1 text-center font-mono text-sm tracking-widest">
                                <button type="button" @click="repair('register')" :disabled="busy"
                                        class="rounded bg-operator px-3 py-1.5 text-xs font-medium text-white disabled:bg-line disabled:text-ink-muted"
                                        x-text="busy ? 'En cours…' : actionLabel('register')"></button>
                            </div>

                            <button x-show="check.action === 'subscribe'" type="button"
                                    @click="repair('subscribe')" :disabled="busy"
                                    class="mt-2 rounded bg-operator px-3 py-1.5 text-xs font-medium text-white disabled:bg-line disabled:text-ink-muted"
                                    x-text="busy ? 'En cours…' : actionLabel('subscribe')"></button>

                            <a x-show="check.doc" :href="check.doc" target="_blank" rel="noopener"
                               class="mt-2 block text-xs text-danger-strong underline underline-offset-2">Documentation Meta</a>
                        </div>
                    </div>
                </template>

                <p x-show="feedback" class="border-b border-line px-4 py-2 text-xs"
                   :class="feedback && feedback.ok ? 'text-read' : 'text-danger-strong'"
                   x-text="feedback ? feedback.message : ''"></p>

                <div class="px-4 py-3">
                    <button type="button" @click="showHelp = ! showHelp"
                            class="text-xs text-ink-muted underline underline-offset-2"
                            x-text="showHelp ? 'Masquer les erreurs d’envoi fréquentes' : 'Erreurs d’envoi fréquentes'"></button>

                    <dl x-show="showHelp" x-cloak class="mt-2 space-y-2">
                        <div>
                            <dt class="font-mono text-xs">131030</dt>
                            <dd class="text-xs leading-relaxed text-ink-muted">
                                Destinataire absent de la liste autorisée. En mode Développement, seuls cinq numéros
                                déclarés peuvent échanger : ajoutez-le dans WhatsApp → API Setup → champ « To »
                                → Manage phone number list. Cette liste n'est pas modifiable par l'API.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-mono text-xs">133010</dt>
                            <dd class="text-xs leading-relaxed text-ink-muted">
                                Numéro émetteur non enregistré auprès de la Cloud API. Le bouton ci-dessus le corrige.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-mono text-xs">131047</dt>
                            <dd class="text-xs leading-relaxed text-ink-muted">
                                Fenêtre de 24 heures fermée. Seul un message template peut relancer la conversation ;
                                la console n'en envoie pas, c'est une action volontairement laissée hors de son périmètre.
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flex items-center justify-between border-t border-line px-4 py-2">
                    <span class="text-xs text-ink-muted"
                          x-text="checkedAt ? 'Vérifié à ' + checkedAt : 'Vérification en cours…'"></span>
                    <button type="button" @click="load(true)" class="text-xs underline underline-offset-2">Revérifier</button>
                </div>
            </div>
        </div>

        <span class="text-xs text-ink-muted">
            Bot :
            <span class="font-medium text-ink">
                {{ config('whatsapp.bot.enabled') ? config('whatsapp.bot.mode') : 'désactivé' }}
            </span>
        </span>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-ink-muted underline underline-offset-2">Se déconnecter</button>
        </form>
    </div>
</header>
