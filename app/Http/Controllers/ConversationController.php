<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $conversations) {}

    public function index(): View
    {
        return view('conversations.index', [
            'conversations' => $this->sidebar(),
        ]);
    }

    public function show(Conversation $conversation): View
    {
        $conversation->load('contact');
        $this->conversations->markAsRead($conversation);

        return view('conversations.show', [
            'conversations' => $this->sidebar(),
            'conversation' => $conversation,
            'messages' => $conversation->messages()->oldest('id')->get(),
        ]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $message = $this->conversations->replyAsOperator($conversation, trim($validated['body']));
        } catch (Throwable $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], 422)
                : back()->withInput()->withErrors(['body' => $exception->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['message' => $message->toPayload()])
            : redirect()->route('conversations.show', $conversation);
    }

    /**
     * Liste de gauche : la conversation la plus récente en tête.
     *
     * @return Collection<int, Conversation>
     */
    private function sidebar(): Collection
    {
        return Conversation::with(['contact', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }
}
