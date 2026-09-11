<header class="flex h-14 shrink-0 items-center justify-between border-b border-line bg-surface px-6">
    <div class="flex items-baseline gap-3">
        <span class="font-medium">Console WhatsApp</span>
        <span class="font-mono text-xs text-ink-muted">Phone ID {{ config('whatsapp.phone_id') ?: 'non configuré' }}</span>
    </div>

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
                 class="absolute right-0 z-10 mt-2 w-96 rounded-lg border border-line bg-surface p-4 text-left shadow-lg">

                <p class="mb-2 text-sm font-medium">Liaison avec Meta</p>

                <template x-for="check in checks" :key="check.label">
                    <div class="border-t border-line py-2">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="dotClass(check.status)"></span>
                            <span x-text="check.label"></span>
                        </p>
                        <p class="mt-1 text-xs text-ink-muted" x-text="check.detail"></p>
                        <p x-show="check.advice" class="mt-1 text-xs text-danger-strong" x-text="check.advice"></p>
                    </div>
                </template>

                <div class="mt-3 flex items-center justify-between border-t border-line pt-3">
                    <span class="text-xs text-ink-muted" x-text="checkedAt ? 'Vérifié à ' + checkedAt : 'Vérification en cours…'"></span>
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
