<?php

namespace App\Http\Controllers;

use App\Models\RagDocument;
use App\Services\RagKnowledgeService;
use App\Services\RagProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ManagerRagKnowledgeController extends Controller
{
    public function __construct(private RagKnowledgeService $knowledge, private RagProductCatalogService $productCatalog) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = RagDocument::query()->select(['document_key', 'title', 'content', 'is_active', 'updated_at']);
        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('content', 'like', '%'.$search.'%'));
        }

        $documents = $query->orderBy('document_key')->paginate(20)->withQueryString();
        $documents->setCollection($documents->getCollection()->map(fn (RagDocument $document): array => [
            'id' => $document->document_key,
            'title' => $document->title ?: $document->document_key,
            'summary' => $this->summary($document->content, $document->title ?: $document->document_key),
            'managed' => $this->knowledge->isSystemManagedDocument($document->document_key),
            'active' => $document->is_active,
            'updated_at' => $document->updated_at?->setTimezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
        ]));
        $pendingSyncCount = RagDocument::withTrashed()->whereNull('synced_at')->count();

        return view('manager.knowledge.index', compact('documents', 'pendingSyncCount', 'search'));
    }

    public function sync(): RedirectResponse
    {
        try {
            $this->productCatalog->stage();
            $this->knowledge->syncPending();
        } catch (RuntimeException $exception) {
            return redirect()->route('manager.knowledge.index')->with('error', 'Tài liệu vẫn được giữ trong MySQL nhưng chatbot chưa đồng bộ. '.$exception->getMessage());
        }

        return redirect()->route('manager.knowledge.index')->with('success', 'Đã đồng bộ các nội dung đang chờ lên chatbot.');
    }

    public function create(): View
    {
        return view('manager.knowledge.form', ['document' => null, 'isEditing' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $documentId = Str::slug($data['title']);
        if ($documentId === '') {
            return back()->withInput()->with('error', 'Tiêu đề cần có chữ hoặc số để tạo mã tài liệu.');
        }

        try {
            if ($this->documentExists($documentId)) {
                return back()->withInput()->with('error', 'Tiêu đề này đã có tài liệu. Hãy đổi tiêu đề để tạo mã khác.');
            }
            $this->knowledge->create($documentId, $this->markdown($data['title'], $data['content']));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return $this->syncAfterSave('Đã lưu tài liệu vào MySQL và đồng bộ chatbot.');
    }

    public function createFaq(): View
    {
        return view('manager.knowledge.faq-form', ['document' => null, 'isEditing' => false]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $data = $this->validateFaq($request);
        $documentId = $this->faqId($data['topic'], $data['question']);

        try {
            if ($this->documentExists($documentId)) {
                return back()->withInput()->with('error', 'Câu hỏi này đã có tài liệu gần giống. Hãy mở tài liệu đó để sửa hoặc thay đổi câu hỏi.');
            }
            $this->knowledge->create($documentId, $this->faqMarkdown($data['topic'], $data['question'], $data['answer']));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return $this->syncAfterSave('Đã lưu câu hỏi thường gặp vào MySQL và đồng bộ chatbot.');
    }

    public function edit(string $document): View|RedirectResponse
    {
        abort_if($this->knowledge->isSystemManagedDocument($document), 403);

        $storedDocument = RagDocument::query()->where('document_key', $document)->firstOrFail();

        $faq = $this->parseFaq($storedDocument->content);
        if ($faq !== null) {
            return view('manager.knowledge.faq-form', [
                'document' => ['id' => $document, ...$faq, 'active' => $storedDocument->is_active],
                'isEditing' => true,
            ]);
        }

        [$title, $content] = $this->splitMarkdown($storedDocument->content);

        return view('manager.knowledge.form', [
            'document' => ['id' => $document, 'title' => $title, 'content' => $content, 'active' => $storedDocument->is_active],
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, string $document): RedirectResponse
    {
        abort_if($this->knowledge->isSystemManagedDocument($document), 403);
        $data = $this->validateDocument($request);

        try {
            $this->knowledge->update($document, $this->markdown($data['title'], $data['content']));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return $this->syncAfterSave('Đã lưu tài liệu vào MySQL và đồng bộ chatbot.');
    }

    public function updateFaq(Request $request, string $document): RedirectResponse
    {
        abort_if($this->knowledge->isSystemManagedDocument($document), 403);
        $data = $this->validateFaq($request);

        try {
            $this->knowledge->update($document, $this->faqMarkdown($data['topic'], $data['question'], $data['answer']));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return $this->syncAfterSave('Đã lưu câu hỏi vào MySQL và đồng bộ chatbot.');
    }

    public function toggle(Request $request, string $document): RedirectResponse
    {
        abort_if($this->knowledge->isSystemManagedDocument($document), 403);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        try {
            $this->knowledge->setActive($document, (bool) $data['active']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $this->syncAfterSave($data['active'] ? 'Đã bật tài liệu trong chatbot.' : 'Đã tạm ngừng tài liệu trong chatbot.');
    }

    public function destroy(string $document): RedirectResponse
    {
        abort_if($this->knowledge->isSystemManagedDocument($document), 403);

        try {
            $this->knowledge->delete($document);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $this->syncAfterSave('Đã xóa tài liệu khỏi chatbot.');
    }

    public function downloadTemplate(): RedirectResponse|StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $faqSheet = $spreadsheet->getActiveSheet();
        $faqSheet->setTitle('Câu hỏi');
        $headers = ['Mã', 'Chủ đề', 'Câu hỏi', 'Câu trả lời', 'Trạng thái'];
        foreach ($headers as $index => $header) {
            $faqSheet->getCell(Coordinate::stringFromColumnIndex($index + 1).'1')->setValueExplicit($header, DataType::TYPE_STRING);
        }
        $faqSheet->getStyle('A1:E1')->getFont()->setBold(true);
        $faqSheet->freezePane('A2');
        $faqSheet->setAutoFilter('A1:E1');
        foreach (['A' => 30, 'B' => 24, 'C' => 42, 'D' => 64, 'E' => 18] as $column => $width) {
            $faqSheet->getColumnDimension($column)->setWidth($width);
        }

        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Cách nhập');
        $guideSheet->fromArray([
            ['Cột', 'Cách điền'],
            ['Mã', 'Để trống khi thêm câu hỏi mới. Dùng mã có sẵn khi muốn sửa tài liệu.'],
            ['Chủ đề', 'Ví dụ: Gói tập, Thanh toán, Tài khoản.'],
            ['Câu hỏi', 'Viết một câu khách hàng có thể hỏi.'],
            ['Câu trả lời', 'Viết hướng dẫn chính xác, công khai và dễ hiểu.'],
            ['Trạng thái', 'Điền Đang dùng hoặc Tạm ngừng.'],
            ['Ví dụ', 'Gói tập | Gói một tháng có hạn bao lâu? | Gói có hiệu lực theo ngày bắt đầu được chọn. | Đang dùng'],
        ], null, 'A1');
        $guideSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $guideSheet->getColumnDimension('A')->setWidth(22);
        $guideSheet->getColumnDimension('B')->setWidth(100);

        $response = response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'mau-tai-lieu-chatbot.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return $response;
    }

    public function previewImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($validated['file']->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $lastRow = $sheet->getHighestDataRow();
            if ($lastRow > 101) {
                $spreadsheet->disconnectWorksheets();

                return $this->storeImportPreview($request, [], [], [['row' => 102, 'message' => 'Mỗi lần chỉ nhập tối đa 100 dòng dữ liệu.']]);
            }
            $rows = $sheet->rangeToArray('A1:E'.max(1, $lastRow), null, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable) {
            return back()->with('error', 'Không đọc được file Excel. Hãy tải mẫu mới và lưu ở định dạng .xlsx.');
        }

        $expectedHeaders = ['mã', 'chủ đề', 'câu hỏi', 'câu trả lời', 'trạng thái'];
        $actualHeaders = array_map(fn (mixed $value): string => Str::lower(trim((string) $value)), $rows[0] ?? []);
        if ($actualHeaders !== $expectedHeaders) {
            return $this->storeImportPreview($request, [], [], [[
                'row' => 1,
                'message' => 'Tiêu đề cột chưa đúng. Hãy dùng file mẫu và giữ đủ 5 cột theo đúng thứ tự.',
            ]]);
        }

        try {
            $this->knowledge->importLegacyDocumentsOnce();
        } catch (RuntimeException $exception) {
            return back()->with('error', 'Chưa thể đối chiếu tài liệu cũ. Hãy kiểm tra dịch vụ chatbot rồi thử lại: '.$exception->getMessage());
        }

        $existingIds = RagDocument::withTrashed()->pluck('document_key')->all();

        $documents = [];
        $previewRows = [];
        $errors = [];
        $seenIds = [];
        foreach (array_slice($rows, 1, 100, true) as $offset => $row) {
            $values = array_map(fn (mixed $value): string => trim((string) ($value ?? '')), array_slice([...$row, '', '', '', '', ''], 0, 5));
            [$documentId, $topic, $question, $answer, $status] = $values;
            if (implode('', $values) === '') {
                continue;
            }

            $rowNumber = $offset + 1;
            $rowHasError = false;
            foreach (['Chủ đề' => $topic, 'Câu hỏi' => $question, 'Câu trả lời' => $answer, 'Trạng thái' => $status] as $label => $value) {
                if ($value === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Thiếu trường '.$label.'.'];
                    $rowHasError = true;
                }
            }
            if (mb_strlen($topic) > 160 || mb_strlen($question) > 500 || mb_strlen($answer) > 20_000) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Chủ đề tối đa 160, câu hỏi 500 và câu trả lời 20.000 ký tự.'];
                $rowHasError = true;
            }

            $active = match (Str::lower($status)) {
                'đang dùng', 'active', 'on' => true,
                'tạm ngừng', 'paused', 'off' => false,
                default => null,
            };
            if ($active === null) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Trạng thái chỉ nhận “Đang dùng” hoặc “Tạm ngừng”.'];
                $rowHasError = true;
            }

            if ($documentId === '') {
                $documentId = $this->faqId($topic, $question);
                if ($documentId === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Mã tự tạo không hợp lệ. Hãy bổ sung chữ hoặc số vào chủ đề/câu hỏi.'];
                    $rowHasError = true;
                }
            } elseif (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $documentId)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Mã chỉ dùng chữ thường không dấu, số và dấu gạch nối.'];
                $rowHasError = true;
            }
            if (isset($seenIds[$documentId])) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Mã này bị trùng với dòng '.$seenIds[$documentId].'.'];
                $rowHasError = true;
            }
            if ($values[0] === '' && in_array($documentId, $existingIds, true)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Mã tự tạo đã tồn tại. Hãy điền mã ở cột đầu nếu bạn muốn sửa tài liệu đó.'];
                $rowHasError = true;
            }

            $seenIds[$documentId] = $rowNumber;
            if ($rowHasError) {
                continue;
            }

            $content = $this->faqMarkdown($topic, $question, $answer);
            $documents[] = ['id' => $documentId, 'content' => $content, 'active' => $active];
            $previewRows[] = [
                'row' => $rowNumber,
                'id' => $documentId,
                'topic' => $topic,
                'question' => $question,
                'action' => in_array($documentId, $existingIds, true) ? 'update' : 'create',
                'active' => $active,
            ];
        }

        if ($previewRows === [] && $errors === []) {
            $errors[] = ['row' => 2, 'message' => 'File chưa có dòng câu hỏi nào để nhập.'];
        }

        return $this->storeImportPreview($request, $documents, $previewRows, $errors);
    }

    public function importReview(Request $request): View|RedirectResponse
    {
        $preview = $request->session()->get('rag_import_preview');
        if (! is_array($preview)) {
            return redirect()->route('manager.knowledge.index')->with('error', 'Chưa có file nào được kiểm tra.');
        }

        return view('manager.knowledge.import-review', compact('preview'));
    }

    public function confirmImport(Request $request): RedirectResponse
    {
        $preview = $request->session()->get('rag_import_preview');
        if (! is_array($preview) || ! empty($preview['errors']) || empty($preview['documents'])) {
            return redirect()->route('manager.knowledge.import.review')
                ->with('error', 'Xử lý các lỗi và kiểm tra lại file trước khi xác nhận.');
        }

        try {
            $this->knowledge->batchUpsert($preview['documents']);
            $this->knowledge->syncPending();
        } catch (RuntimeException $exception) {
            return redirect()->route('manager.knowledge.import.review')
                ->with('error', 'Tài liệu đã được lưu trong MySQL nhưng chatbot chưa đồng bộ. Thử xác nhận lại sau khi dịch vụ hoạt động: '.$exception->getMessage());
        }

        $request->session()->forget('rag_import_preview');

        return redirect()->route('manager.knowledge.index')->with('success', 'Đã nhập tài liệu vào MySQL và đồng bộ chatbot.');
    }

    private function storeImportPreview(Request $request, array $documents, array $rows, array $errors): RedirectResponse
    {
        $request->session()->put('rag_import_preview', [
            'documents' => $documents,
            'rows' => $rows,
            'errors' => $errors,
            'new_count' => count(array_filter($rows, fn (array $row): bool => $row['action'] === 'create')),
            'update_count' => count(array_filter($rows, fn (array $row): bool => $row['action'] === 'update')),
        ]);

        return redirect()->route('manager.knowledge.import.review');
    }

    /** @return array{topic: string, question: string, answer: string} */
    private function validateFaq(Request $request): array
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'question' => ['required', 'string', 'max:500', 'not_regex:/[\r\n]/'],
            'answer' => ['required', 'string', 'max:20000'],
        ]);

        if (strlen($this->faqMarkdown($data['topic'], $data['question'], $data['answer'])) > 200_000) {
            throw ValidationException::withMessages(['answer' => 'Câu trả lời vượt quá giới hạn 200 KB.']);
        }

        return $data;
    }

    private function faqId(string $topic, string $question): string
    {
        return trim(substr(Str::slug($topic.'-'.$question), 0, 120), '-');
    }

    private function documentExists(string $documentId): bool
    {
        return RagDocument::withTrashed()->where('document_key', $documentId)->exists();
    }

    private function syncAfterSave(string $successMessage): RedirectResponse
    {
        try {
            $this->knowledge->syncPending();
        } catch (RuntimeException $exception) {
            return redirect()->route('manager.knowledge.index')->with('error', 'Dữ liệu vẫn được giữ trong MySQL nhưng chatbot chưa đồng bộ. Tải lại trang sau khi dịch vụ hoạt động: '.$exception->getMessage());
        }

        return redirect()->route('manager.knowledge.index')->with('success', $successMessage);
    }

    private function summary(string $content, string $fallback): string
    {
        if (preg_match('/\*\*Câu hỏi:\*\*\s*(.+?)(?:\R+\*\*Câu trả lời:\*\*|\z)/su', $content, $matches) === 1) {
            return Str::limit(trim(preg_replace('/\s+/u', ' ', $matches[1]) ?? $matches[1]), 180);
        }

        $body = preg_replace('/^#\s*[^\r\n]*(?:\R|$)/u', '', $content, 1) ?? $content;
        $body = str_replace(['**Câu trả lời:**', '**'], '', $body);
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);

        return Str::limit($body !== '' ? $body : $fallback, 180);
    }

    private function faqMarkdown(string $topic, string $question, string $answer): string
    {
        return '# '.trim($topic)."\n\n**Câu hỏi:** ".trim($question)."\n\n**Câu trả lời:**\n".trim($answer);
    }

    /** @return array{topic: string, question: string, answer: string}|null */
    private function parseFaq(string $content): ?array
    {
        if (! preg_match('/^# ([^\r\n]+)\R\R\*\*Câu hỏi:\*\* ([^\r\n]+)\R\R\*\*Câu trả lời:\*\*\R?(.*)$/su', trim($content), $matches)) {
            return null;
        }

        return ['topic' => trim($matches[1]), 'question' => trim($matches[2]), 'answer' => trim($matches[3])];
    }

    /** @return array{title: string, content: string} */
    private function validateDocument(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'content' => ['required', 'string', 'max:199000'],
        ]);
        if (strlen($this->markdown($data['title'], $data['content'])) > 200_000) {
            throw ValidationException::withMessages(['content' => 'Nội dung tài liệu vượt quá giới hạn 200 KB.']);
        }

        return $data;
    }

    private function markdown(string $title, string $content): string
    {
        return '# '.trim($title)."\n\n".trim($content);
    }

    /** @return array{string, string} */
    private function splitMarkdown(string $content): array
    {
        $lines = preg_split('/\R/', trim($content)) ?: [];
        if (isset($lines[0]) && str_starts_with($lines[0], '# ')) {
            $title = trim(substr(array_shift($lines), 2));
            while (isset($lines[0]) && trim($lines[0]) === '') {
                array_shift($lines);
            }

            return [$title, trim(implode("\n", $lines))];
        }

        return ['Tài liệu chatbot', trim($content)];
    }
}
