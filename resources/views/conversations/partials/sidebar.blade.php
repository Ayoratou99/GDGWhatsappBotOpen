@php($activeId = $conversation->id ?? null)

<aside class="flex w-80 shrink-0 flex-col border-r border-line bg-surface"
       x-data="conversationList(@js($conversations->map->toSidebarPayload()), @js($activeId))">

    <div class="flex h-14 items-center border-b border-line px-4">
        <h2 class="text-sm font-medium">Conversations</h2>
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
