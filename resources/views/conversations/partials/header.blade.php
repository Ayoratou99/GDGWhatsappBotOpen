<header class="flex h-14 shrink-0 items-center justify-between border-b border-line bg-surface px-6">
    <div class="flex items-baseline gap-3">
        <span class="font-medium">Console WhatsApp</span>
        <span class="font-mono text-xs text-ink-muted">Phone ID {{ config('whatsapp.phone_id') ?: 'non configuré' }}</span>
    </div>

    <div class="flex items-center gap-4">
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
