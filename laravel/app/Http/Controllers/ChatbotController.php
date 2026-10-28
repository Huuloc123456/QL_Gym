<?php

namespace App\Http\Controllers;

use App\Services\GymChatAnswerService;
use App\Services\GymPublicInfoService;
use App\Services\RagProductCatalogService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function __invoke(Request $request, RagProductCatalogService $productCatalog, GymPublicInfoService $publicInfo, GymChatAnswerService $answers): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);
        $message = trim($validated['message']);
        $productCards = [];
        $privateAnswer = $answers->answerPrivateAccountQuestion($message);
        if ($privateAnswer !== null) {
            return response()->json($privateAnswer);
        }
        $authoritativeAnswer = $answers->answerAuthoritativeBusinessQuestion($message);
        if ($authoritativeAnswer !== null) {
            return response()->json($authoritativeAnswer + ['products' => []]);
        }
        $purchaseGuide = $productCatalog->purchaseHowToAnswer($message);
        if ($purchaseGuide !== null) {
            return response()->json([
                'answer' => $purchaseGuide,
                'sources' => ['Hướng dẫn mua hàng'],
                'products' => [],
            ]);
        }

        $packageAnswer = $productCatalog->answerPackageQuestion($message);
        if ($packageAnswer !== null) {
            return response()->json([
                'answer' => $packageAnswer,
                'sources' => [str_contains($packageAnswer, 'ước tính')
                    ? 'Ước tính từ giá gói 1 tháng đang bán'
                    : 'Gói tập hiện đang mở bán'],
                'products' => [],
            ]);
        }

        $publicAnswer = $publicInfo->answerQuestion($message);
        if ($publicAnswer !== null) {
            return response()->json([
                'answer' => $publicAnswer,
                'sources' => ['Thông tin công khai The Gym'],
                'products' => [],
            ]);
        }

        if ($purchaseGuide === null && $productCatalog->isProductRelatedQuestion($message)) {
            $productCards = $productCatalog->cardsForQuestion($message);

            $catalogAnswer = $productCatalog->answerOverviewQuestion($message);
            if ($catalogAnswer !== null) {
                return response()->json([
                    'answer' => $catalogAnswer,
                    'sources' => ['Danh mục sản phẩm The Gym'],
                    'products' => $productCards,
                ]);
            }

            $productCardAnswer = $productCatalog->answerProductCardQuestion($message, $productCards);
            if ($productCardAnswer !== null) {
                return response()->json([
                    'answer' => $productCardAnswer,
                    'sources' => ['Danh mục sản phẩm The Gym'],
                    'products' => $productCards,
                ]);
            }
        }

        $local = $answers->answer($message);
        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout((int) config('services.rag.timeout', 30))
                ->post(rtrim((string) config('services.rag.url'), '/').'/chat', [
                    'message' => $message,
                ]);
        } catch (ConnectionException) {
            if ($local !== null) {
                return response()->json($local);
            }

            return response()->json([
                'message' => 'Trợ lý đang bận. Bạn thử lại sau nhé.',
            ], 503);
        }

        $answer = $response->json('answer');
        if (! $response->successful() || ! is_string($answer) || trim($answer) === '') {
            if ($local !== null) {
                return response()->json($local);
            }

            return response()->json([
                'message' => 'Trợ lý đang bận. Bạn thử lại sau nhé.',
            ], 503);
        }

        $sources = $response->json('sources', []);

        return response()->json([
            'answer' => $answer,
            'products' => $productCards,
            'sources' => is_array($sources)
                ? array_values(array_filter($sources, static fn (mixed $source): bool => is_string($source)))
                : [],
        ]);
    }
}
