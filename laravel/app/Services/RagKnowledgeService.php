<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RagKnowledgeService
{
    /** @return list<array{id: string, title: string, content: string, active: bool, updated_at?: string}> */
    public function documents(): array
    {
        $documents = $this->send('GET', '/admin/documents', authenticated: true)['documents'] ?? null;
        if (! is_array($documents)) {
            throw new RuntimeException('Dịch vụ chatbot trả về danh sách tài liệu không hợp lệ.');
        }

        return $documents;
    }

    /** @return array<string, mixed> */
    public function create(string $id, string $content): array
    {
        return $this->send('POST', '/admin/documents', ['id' => $id, 'content' => $content], authenticated: true);
    }

    /** @return array<string, mixed> */
    public function update(string $id, string $content): array
    {
        return $this->send('PUT', '/admin/documents/'.rawurlencode($id), ['content' => $content], authenticated: true);
    }

    /** @return array<string, mixed> */
    public function setActive(string $id, bool $active): array
    {
        return $this->send('PATCH', '/admin/documents/'.rawurlencode($id).'/status', ['active' => $active], authenticated: true);
    }

    /** @return array<string, mixed> */
    public function delete(string $id): array
    {
        return $this->send('DELETE', '/admin/documents/'.rawurlencode($id), authenticated: true);
    }

    /** @param list<array{id: string, content: string, active: bool}> $documents
     * @return array<string, mixed>
     */
    public function batchUpsert(array $documents): array
    {
        return $this->send('POST', '/admin/documents/batch', ['documents' => $documents], authenticated: true);
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
