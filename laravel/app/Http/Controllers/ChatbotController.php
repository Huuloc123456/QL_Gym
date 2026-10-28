<?php

namespace App\Http\Controllers;

use App\Services\GymChatAnswerService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function __invoke(Request $request, GymChatAnswerService $answers): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $local = $answers->answer(trim($validated['message']));
        if ($local !== null) {
            return response()->json($local);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout((int) config('services.rag.timeout', 30))
                ->post(rtrim((string) config('services.rag.url'), '/').'/chat', [
                    'message' => trim($validated['message']),
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Trợ lý đang bận. Bạn thử lại sau nhé.',
            ], 503);
        }

        $answer = $response->json('answer');
        if (! $response->successful() || ! is_string($answer) || trim($answer) === '') {
            return response()->json([
                'message' => 'Trợ lý đang bận. Bạn thử lại sau nhé.',
            ], 503);
        }

        $sources = $response->json('sources', []);

        return response()->json([
            'answer' => $answer,
            'sources' => is_array($sources)
                ? array_values(array_filter($sources, static fn (mixed $source): bool => is_string($source)))
                : [],
        ]);
    }
}
