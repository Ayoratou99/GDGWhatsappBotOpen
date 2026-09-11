@extends('layouts.app')

@section('title', 'Conversations — Console WhatsApp')

@section('content')
    <div class="flex h-full flex-col">
        @include('conversations.partials.header')

        <div class="flex min-h-0 flex-1">
            @include('conversations.partials.sidebar')

            <main class="flex flex-1 items-center justify-center px-8">
                @if ($conversations->isEmpty())
                    <p class="max-w-md text-center text-ink-muted">
                        Aucune conversation pour l'instant.<br>
                        Envoyez un message WhatsApp au numéro connecté pour démarrer.
                    </p>
                @else
                    <p class="text-ink-muted">Sélectionnez une conversation à gauche.</p>
                @endif
            </main>
        </div>
    </div>
@endsection
