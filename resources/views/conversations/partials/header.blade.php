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
                 class="absolute right-0 z-10 mt-2 w-[22rem] rounded-lg border border-line bg-surface text-left shadow-md">

                <div class="flex items-baseline justify-between px-4 pt-3">
                    <span class="text-xs font-medium">Liaison avec Meta</span>
                    <span class="font-mono text-[11px] text-ink-muted">{{ config('whatsapp.phone_id') ?: '—' }}</span>
                </div>

                <div class="px-4 py-2">
                    <template x-for="check in checks" :key="check.label">
                        <div class="py-1.5">
                            <p class="flex items-center gap-2 text-xs">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full" :class="dotClass(check.status)"></span>
                                <span class="font-medium" x-text="check.label"></span>
                            </p>
                            <p class="mt-0.5 pl-3.5 text-[11px] leading-relaxed text-ink-muted" x-text="check.detail"></p>

                            {{-- Encadré et bouton n'apparaissent qu'en cas de panne : un état
                                 nominal ne doit rien réclamer à l'opérateur. --}}
                            <div x-show="check.advice" class="mt-2 rounded border border-danger bg-danger-soft px-3 py-2">
                                <p class="text-[11px] leading-relaxed text-danger-strong" x-text="check.advice"></p>

                                <div x-show="check.action === 'register'" class="mt-2 flex items-center gap-2">
                                    <label :for="'pin-' + check.label" class="sr-only">PIN à six chiffres</label>
                                    <input :id="'pin-' + check.label" x-model="pin" type="text" inputmode="numeric"
                                           maxlength="6" placeholder="······"
                                           class="w-20 rounded border border-line bg-surface px-2 py-1 text-center font-mono text-xs tracking-widest">
                                    <button type="button" @click="repair('register')" :disabled="busy"
                                            class="rounded bg-operator px-3 py-1.5 text-[11px] font-medium text-white disabled:bg-line disabled:text-ink-muted"
                                            x-text="busy ? 'En cours…' : actionLabel('register')"></button>
                                </div>

                                <button x-show="check.action === 'subscribe'" type="button"
                                        @click="repair('subscribe')" :disabled="busy"
                                        class="mt-2 rounded bg-operator px-3 py-1.5 text-[11px] font-medium text-white disabled:bg-line disabled:text-ink-muted"
                                        x-text="busy ? 'En cours…' : actionLabel('subscribe')"></button>

                                <a x-show="check.doc" :href="check.doc" target="_blank" rel="noopener"
                                   class="mt-2 block text-[11px] text-danger-strong underline underline-offset-2">Documentation Meta</a>
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="feedback" class="px-4 pb-2 text-[11px]"
                   :class="feedback && feedback.ok ? 'text-read' : 'text-danger-strong'"
                   x-text="feedback ? feedback.message : ''"></p>

                {{-- La documentation des codes d'erreur ne sert qu'à qui vient d'en croiser un. --}}
                <div x-show="status === 'error'" class="border-t border-line px-4 py-2">
                    <button type="button" @click="showHelp = ! showHelp"
                            class="text-[11px] text-ink-muted underline underline-offset-2"
                            x-text="showHelp ? 'Masquer les erreurs fréquentes' : 'Erreurs d’envoi fréquentes'"></button>

                    <dl x-show="showHelp" x-cloak class="mt-2 space-y-2">
                        <div>
                            <dt class="font-mono text-[11px]">131030</dt>
                            <dd class="text-[11px] leading-relaxed text-ink-muted">
                                Destinataire absent de la liste autorisée. En mode Développement, cinq numéros déclarés
                                au maximum : WhatsApp → API Setup → champ « To ». Cette liste n'est pas modifiable par l'API.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-mono text-[11px]">133010</dt>
                            <dd class="text-[11px] leading-relaxed text-ink-muted">
                                Numéro émetteur non enregistré auprès de la Cloud API. Le bouton ci-dessus le corrige.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-mono text-[11px]">131047</dt>
                            <dd class="text-[11px] leading-relaxed text-ink-muted">
                                Fenêtre de 24 heures fermée. Seul un modèle peut relancer la conversation : c'est ce que
                                fait l'action « Inviter un numéro ».
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flex items-center justify-between border-t border-line px-4 py-1.5">
                    <span class="text-[11px] text-ink-muted"
                          x-text="checkedAt ? 'Vérifié à ' + checkedAt : 'Vérification…'"></span>
                    <button type="button" @click="load(true)" class="text-[11px] text-ink-muted underline underline-offset-2">
                        Actualiser
                    </button>
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
