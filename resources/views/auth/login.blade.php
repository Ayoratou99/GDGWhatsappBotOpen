@extends('layouts.app')

@section('title', 'Connexion — Console WhatsApp')

@section('content')
    <main class="flex min-h-full items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-medium">Console WhatsApp</h1>
            <p class="mt-1 text-sm text-ink-muted">Accès réservé à l'opérateur.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4 rounded-lg border border-line bg-surface p-6">
                @csrf

                @error('username')
                    <p class="rounded border border-danger bg-danger-soft px-3 py-2 text-sm text-danger-strong">{{ $message }}</p>
                @enderror

                <div>
                    <label for="username" class="block text-sm font-medium">Identifiant</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" autofocus required
                           autocomplete="username"
                           class="mt-1 w-full rounded border border-line px-3 py-2 text-[15px] focus:border-operator focus:outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Mot de passe</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="mt-1 w-full rounded border border-line px-3 py-2 text-[15px] focus:border-operator focus:outline-none">
                </div>

                <button type="submit"
                        class="w-full rounded bg-operator px-4 py-2 font-medium text-white">
                    Se connecter
                </button>
            </form>
        </div>
    </main>
@endsection
