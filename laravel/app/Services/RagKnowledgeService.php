<?php

namespace App\Services;

use App\Models\RagDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RagKnowledgeService
{
    public const PRODUCT_CATALOG_PREFIX = 'managed-products-';

    /** @return list<array{id: string, title: string, content: string, active: bool, updated_at?: string}> */
    public function documents(): array
    {
        $documents = RagDocument::query()->orderBy('document_key')->get();
        $hasStoredDocuments = RagDocument::withTrashed()->exists();

        if ($documents->isEmpty() && ! $hasStoredDocuments) {
            $legacy = $this->send('GET', '/admin/documents', authenticated: true)['documents'] ?? null;
            if (! is_array($legacy)) {
                throw new RuntimeException('Dịch vụ chatbot trả về danh sách tài liệu không hợp lệ.');
            }

            foreach ($legacy as $document) {
                if (! is_array($document) || ! is_string($document['id'] ?? null) || ! is_string($document['content'] ?? null)) {
                    continue;
                }
                $stored = RagDocument::query()->firstOrCreate(
                    ['document_key' => $document['id']],
                    [
                        'title' => is_string($document['title'] ?? null) ? $document['title'] : $document['id'],
                        'content' => $document['content'],
                        'is_active' => (bool) ($document['active'] ?? true),
                        'synced_at' => now(),
                        'created_at' => isset($document['updated_at']) ? Carbon::parse($document['updated_at']) : now(),
                        'updated_at' => isset($document['updated_at']) ? Carbon::parse($document['updated_at']) : now(),
                    ],
                );
                if (isset($document['updated_at'])) {
                    $stored->forceFill(['updated_at' => Carbon::parse($document['updated_at'])])->saveQuietly();
                }
            }

            $documents = RagDocument::query()->orderBy('document_key')->get();
            if ($documents->isEmpty()) {
                return $legacy;
            }
        }
        if ($documents->isNotEmpty()) {
            return $documents->map(fn (RagDocument $document): array => [
                'id' => $document->document_key,
                'title' => $document->title,
                'content' => $document->content,
                'active' => $document->is_active,
                'managed' => $this->isSystemManagedDocument($document->document_key),
                'updated_at' => $document->updated_at?->toIso8601String(),
            ])->all();
        }

        return [];
    }

    public function create(string $id, string $content): void
    {
        RagDocument::query()->create([
            'document_key' => $id,
            'title' => $this->title($content, $id),
            'content' => $content,
            'is_active' => true,
        ]);
    }

    public function update(string $id, string $content): void
    {
        $document = RagDocument::query()->where('document_key', $id)->firstOrFail();
        $document->forceFill(['title' => $this->title($content, $id), 'content' => $content, 'synced_at' => null])->save();
    }

    public function setActive(string $id, bool $active): void
    {
        $document = RagDocument::query()->where('document_key', $id)->firstOrFail();
        $document->forceFill(['is_active' => $active, 'synced_at' => null])->save();
    }

    public function delete(string $id): void
    {
        $document = RagDocument::query()->where('document_key', $id)->firstOrFail();
        $document->forceFill(['synced_at' => null])->save();
        $document->delete();
    }

    /** @param list<array{id: string, content: string, active: bool}> $documents
     */
    public function batchUpsert(array $documents): void
    {
        foreach ($documents as $document) {
            RagDocument::query()->withTrashed()->updateOrCreate(
                ['document_key' => $document['id']],
                [
                    'title' => $this->title($document['content'], $document['id']),
                    'content' => $document['content'],
                    'is_active' => $document['active'],
                    'deleted_at' => null,
                    'synced_at' => null,
                ],
            );
        }
    }

    private function title(string $content, string $fallback): string
    {
        preg_match('/^#\\s+(.+)$/m', $content, $matches);

        return mb_substr(trim($matches[1] ?? $fallback), 0, 180);
    }

    public function isSystemManagedDocument(string $id): bool
    {
        return str_starts_with($id, self::PRODUCT_CATALOG_PREFIX);
    }

    public function syncPending(): void
    {
        $pending = RagDocument::withTrashed()->whereNull('synced_at')->orderBy('id')->get();
        if ($pending->isEmpty()) {
            return;
        }

        $activeDocuments = $pending->whereNull('deleted_at')->values();
        foreach ($activeDocuments->chunk(100) as $chunk) {
            $this->send('POST', '/admin/documents/batch', [
                'documents' => $chunk->map(fn (RagDocument $document): array => [
                    'id' => $document->document_key,
                    'content' => $document->content,
                    'active' => $document->is_active,
                ])->all(),
            ], authenticated: true);
            RagDocument::query()->whereIn('id', $chunk->pluck('id'))->update(['synced_at' => now()]);
        }

        foreach ($pending->whereNotNull('deleted_at') as $document) {
            $this->send('DELETE', '/admin/documents/'.rawurlencode($document->document_key), authenticated: true);
            $document->forceFill(['synced_at' => now()])->save();
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, ?array $payload = null, bool $authenticated = false): array
    {
        if ($method !== 'GET' && function_exists('set_time_limit')) {
            set_time_limit((int) config('services.rag.admin_timeout', 180) + 15);
        }

        $request = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout((float) config('services.rag.admin_timeout', 180));

        if ($authenticated) {
            $token = (string) config('services.rag.admin_token', '');
            if ($token === '') {
                throw new RuntimeException('Chưa cấu hình khóa quản trị dịch vụ chatbot trên máy chủ.');
            }
            $request->withToken($token);
        }

        try {
            $response = $this->dispatch($request, $method, $this->url($path), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Không kết nối được dịch vụ chatbot. Kiểm tra dịch vụ RAG rồi thử lại.', previous: $exception);
        }

        if (! $response->successful()) {
            if ($method === 'DELETE' && $response->status() === 404) {
                return [];
            }

            $message = $response->json('error');
            throw new RuntimeException(is_string($message) ? $message : 'Dịch vụ chatbot chưa thể hoàn tất yêu cầu.');
        }

        $result = $response->json();

        return is_array($result) ? $result : [];
    }

    /** @param array<string, mixed>|null $payload */
    private function dispatch(PendingRequest $request, string $method, string $url, ?array $payload): Response
    {
        return match ($method) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload ?? []),
            'PUT' => $request->put($url, $payload ?? []),
            'PATCH' => $request->patch($url, $payload ?? []),
            'DELETE' => $request->delete($url),
            default => throw new RuntimeException('Phương thức quản trị chatbot không được hỗ trợ.'),
        };
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.rag.url', 'http://127.0.0.1:8090'), '/').$path;
    }
}
