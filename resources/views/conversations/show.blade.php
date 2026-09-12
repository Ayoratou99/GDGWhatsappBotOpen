@extends('layouts.app')

@section('title', $conversation->displayName().' — Console WhatsApp')

@section('content')
    <div class="flex h-full flex-col">
        @include('conversations.partials.header')

        <div class="flex min-h-0 flex-1">
            @include('conversations.partials.sidebar')

            <main class="flex min-w-0 flex-1 flex-col"
                  x-data="conversationThread(
                      {{ $conversation->id }},
                      @js($messages->map->toPayload()),
                      @js($conversation->windowExpiresAt()?->toIso8601String())
                  )">

                <div class="flex h-14 shrink-0 items-center gap-3 border-b border-line bg-surface px-6">
                    <h1 class="font-medium">{{ $conversation->displayName() }}</h1>
                    <span class="font-mono text-xs text-ink-muted">+{{ $conversation->contact->wa_id }}</span>

                    <div class="ml-auto flex items-center gap-3 text-xs">
                        <button type="button" x-show="! confirmClear" @click="confirmClear = true"
                                class="text-ink-muted underline underline-offset-2">
                            Vider la conversation
                        </button>

                        <template x-if="confirmClear">
                            <span class="flex items-center gap-3">
                                <span class="text-danger-strong">Supprimer tous les messages de ce fil ?</span>
                                <button type="button" @click="clear()" :disabled="clearing"
                                        class="rounded bg-danger px-3 py-1 font-medium text-white disabled:bg-line disabled:text-ink-muted"
                                        x-text="clearing ? 'Suppression…' : 'Confirmer'"></button>
                                <button type="button" @click="confirmClear = false"
                                        class="text-ink-muted underline underline-offset-2">Annuler</button>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Bandeau de la fenêtre de 24 h, décompté côté client. --}}
                <div class="flex shrink-0 items-center justify-between px-6 py-3" :class="bannerClass" role="status">
                    <span class="text-sm font-medium" x-text="bannerLabel"></span>
                    <span class="font-mono text-lg font-medium tabular-nums" x-text="countdown"></span>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6" x-ref="thread">
                    <div class="mx-auto flex max-w-3xl flex-col gap-4">
                        <template x-for="message in messages" :key="message.id">
                            <div class="flex flex-col" :class="message.direction === 'inbound' ? 'items-start' : 'items-end'">
                                <span x-show="message.author === 'bot'" class="mb-1 text-xs font-medium text-bot">Bot</span>

                                <div class="max-w-xl rounded-lg bg-surface px-4 py-3 text-[15px] leading-relaxed whitespace-pre-wrap"
                                     :class="[
                                         message.direction === 'inbound' ? 'border border-line border-l-4' : '',
                                         message.author === 'operator' ? 'border border-operator border-r-4' : '',
                                         message.author === 'bot' ? 'border border-bot border-r-4' : '',
                                         message.live && message.direction === 'inbound' ? 'message-in' : '',
                                     ]"
                                     x-text="message.body"></div>

                                <p class="mt-1 text-xs" :class="statusClass(message)">
                                    <span x-text="message.time"></span>
                                    <template x-if="message.direction === 'outbound'">
                                        <span><span aria-hidden="true"> · </span><span x-text="message.status_label"></span></span>
                                    </template>
                                </p>

                                {{-- La cause de l'échec mérite sa propre ligne : sur une seule,
                                     elle noie l'heure et le statut. --}}
                                <p x-show="message.error_message"
                                   class="mt-1 max-w-xl rounded border border-danger bg-danger-soft px-3 py-2 text-xs leading-relaxed text-danger-strong"
                                   x-text="message.error_message"></p>
                            </div>
                        </template>

                        <p x-show="messages.length === 0" class="text-sm text-ink-muted">
                            Aucun message dans cette conversation.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('conversations.messages.store', $conversation) }}"
                      @submit.prevent="send()"
                      class="shrink-0 border-t border-line bg-surface px-6 py-4">
                    @csrf

                    <div class="mx-auto max-w-3xl">
                        @error('body')
                            <p class="mb-2 text-sm text-danger-strong">{{ $message }}</p>
                        @enderror
                        <p x-show="error" x-text="error" class="mb-2 text-sm text-danger-strong"></p>

                        <div class="flex items-end gap-3">
                            <label for="body" class="sr-only">Votre réponse</label>
                            <textarea id="body" name="body" rows="2" x-model="draft" :disabled="!open"
                                      @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send(); }"
                                      :placeholder="open ? 'Répondre en tant qu’opérateur…' : 'Saisie verrouillée'"
                                      class="flex-1 resize-none rounded border border-line px-3 py-2 text-[15px] disabled:bg-app disabled:text-ink-muted"></textarea>

                            <button type="submit" :disabled="!open || sending"
                                    class="rounded bg-operator px-5 py-2.5 font-medium text-white disabled:bg-line disabled:text-ink-muted">
                                Envoyer
                            </button>
                        </div>

                        <p x-show="!open" class="mt-2 text-sm font-medium text-danger-strong">
                            La fenêtre de 24 heures est fermée. Un message template est nécessaire pour relancer la conversation.
                        </p>
                    </div>
                </form>
            </main>
        </div>
    </div>
@endsection
