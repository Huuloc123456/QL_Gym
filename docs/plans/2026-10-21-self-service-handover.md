# Kế hoạch hoàn thiện web để bàn giao vận hành

**Mục tiêu:** Chủ phòng tập tự thay đổi thông tin, nội dung, tri thức chatbot và tài khoản nhân viên trên web; người nhận có thể cài, chạy và khôi phục dữ liệu theo hướng dẫn.

**Kiến trúc:** Laravel/MySQL là nơi quản lý người dùng, cấu hình phòng tập và banner. Dịch vụ RAG Python hiện nằm ở `D:\Learning\Projects\SimpleRAGChatbot`; Laravel gọi API quản trị nội bộ của dịch vụ này để quản lý tài liệu. Các trang mới chỉ dành cho vai trò `manager`, giữ phong cách giao diện hiện có.

**Công nghệ:** Laravel 13, Blade, Tailwind, MySQL, Python, FAISS. Không thêm thư viện nếu API và các thành phần hiện tại đã đáp ứng được.

## Hiện trạng và phạm vi

- `newrepo/main` ở commit `51c760f` ngày kiểm tra 21/10/2026, trùng commit hiện có trên máy. Chưa có bản GitHub mới cần nhập.
- Web đã quản lý gói tập (tạo/sửa/ngừng bán), đơn, lượt tập và ba quyền nhân viên. Không xây thêm hàng hóa, tồn kho, PT, lớp, chi nhánh hay cổng thanh toán trong kế hoạch này.
- Banner đang ghi cố định trong `laravel/resources/views/layouts/app.blade.php`. Chatbot chỉ chuyển câu hỏi qua `laravel/app/Http/Controllers/ChatbotController.php`.
- RAG chỉ nạp Markdown từ `knowledge_base/public/`; cần chạy `embed_pdfs.py` để tạo lại FAISS, và `rag_service.py` giữ bộ truy xuất trong cache khi chạy. Vì thế thêm/xóa file đơn thuần chưa làm câu trả lời đổi ngay.
- Repo RAG hiện có thay đổi chưa commit. Khi triển khai phải giữ nguyên các thay đổi này và kiểm tra lại trạng thái trước mỗi commit.

## Nguyên tắc chung

1. Trước khi sửa giao diện, làm bản xem trước màn quản lý theo giao diện The Gym hiện có và cho người dùng duyệt, theo yêu cầu UX/UI đã thống nhất.
2. Mọi thao tác quản lý phải kiểm tra vai trò ở máy chủ; nhân viên và khách không được gọi URL quản trị trực tiếp.
3. Không xóa lịch sử đơn, gói hội viên hoặc dữ liệu tài khoản khi thay nội dung hiển thị. Không đưa mật khẩu/API key vào HTML, JavaScript hay Git.
4. Mỗi nhóm chức năng hoàn tất khi thao tác qua giao diện được, dữ liệu còn sau khi tải lại trang, tình huống lỗi hiển thị rõ và test liên quan đạt.

## Giai đoạn 1 — Cài đặt phòng tập và hướng dẫn thanh toán

**Việc làm**

1. Tạo bảng `gym_settings` cho tên hiển thị, giới thiệu ngắn, địa chỉ, giờ mở cửa, điện thoại, email liên hệ, tên ngân hàng, số tài khoản và tên người nhận. Chỉ lưu một bản cấu hình; giá trị trống không được bịa ra trên trang công khai.
2. Tạo trang `Quản lý → Cài đặt phòng tập` để xem/sửa các mục trên, validate độ dài và định dạng ở server. Chỉ quản lý có quyền lưu.
3. Trang chủ, footer và hướng dẫn chuyển khoản của đơn đọc từ cấu hình này. Đơn đã ghi nhận vẫn giữ số tiền và trạng thái lịch sử.

**Vị trí dự kiến:** migration và model mới dưới `laravel/database/migrations/`, `laravel/app/Models/`; controller và form dưới `laravel/app/Http/Controllers/`, `laravel/resources/views/manager/`; sửa `laravel/routes/web.php`, `laravel/resources/views/layouts/app.blade.php`, `laravel/resources/views/orders/show.blade.php`.

**Kiểm tra:** test quản lý lưu hợp lệ, nhân viên/khách bị chặn, dữ liệu hiển thị đúng và cấu hình trống không tạo thông tin giả. Thử sửa trực tiếp trên web.

## Giai đoạn 2 — Nội dung công khai và hero banner

**Việc làm**

1. Tạo danh sách banner gồm ảnh, mô tả ảnh, thứ tự và trạng thái bật/tắt. Quản lý có thể tải lên, thay ảnh, sắp xếp, xem trước, ẩn và xóa banner chưa dùng.
2. Chỉ nhận JPEG/PNG/WebP, giới hạn 3 MB mỗi ảnh; kiểm tra MIME và kích thước ở server, lưu qua Laravel Storage. Không cho đường dẫn do người dùng gửi trỏ tới file tùy ý.
3. Carousel đọc banner đang bật từ MySQL. Nếu chưa có banner, hiển thị ảnh mặc định hiện có để trang không bị trống.
4. Cho quản lý sửa phần giới thiệu và FAQ công khai dạng văn bản; dùng Blade escaping khi hiển thị. Nội dung FAQ có thể đưa sang RAG sau khi duyệt và đồng bộ, nhưng không tự suy ra quy tắc nghiệp vụ từ văn bản chưa kiểm tra.

**Vị trí dự kiến:** migration/model banner và FAQ; controller quản lý; `laravel/resources/views/layouts/app.blade.php`, `laravel/resources/views/home.blade.php`, thư mục views quản lý; storage disk công khai.

**Kiểm tra:** upload sai loại/quá dung lượng bị chặn; ảnh bật/tắt và thứ tự đúng trên trang chủ; xóa ảnh không còn bản ghi tham chiếu; thử ở màn hình nhỏ và lớn.

## Giai đoạn 3 — Quản lý tài liệu RAG

**Việc làm**

1. Trên dịch vụ RAG, thêm API nội bộ để liệt kê, tạo, sửa, tạm ngừng và xóa tài liệu Markdown công khai. Mỗi tài liệu có mã ổn định, tiêu đề, trạng thái, thời điểm sửa; không nhận đường dẫn file từ client. Giới hạn kích thước và loại nội dung; chặn tên file bất hợp lệ, tài liệu rỗng và nội dung chứa thông tin nội bộ/cá nhân qua quy trình duyệt của quản lý.
2. Bảo vệ API quản trị bằng secret chỉ lưu ở `.env` của Laravel và RAG; API hỏi đáp công khai không dùng secret này. Chỉ bind RAG vào localhost/mạng nội bộ.
3. Khi thay tập tài liệu, tạo chỉ mục FAISS mới ở vị trí tạm, kiểm tra đọc được rồi thay chỉ mục đang dùng; nếu lỗi, giữ chỉ mục cũ. Xóa cache trong `rag_service.py` hoặc nạp lại bộ truy xuất để câu trả lời dùng dữ liệu mới mà không cần khởi động lại dịch vụ.
4. Trong Laravel, thêm `Quản lý → Tài liệu chatbot`: danh sách, soạn/sửa, bật/tắt, xóa, trạng thái lập chỉ mục, nút thử câu hỏi. Khi RAG ngừng chạy, báo lỗi rõ và không báo lưu thành công giả.
5. Giữ tài liệu nghiệp vụ gốc có phiên bản trong Git; ghi rõ tài liệu nào do quản lý nhập trên web để việc bàn giao và backup không bỏ sót.

**Vị trí dự kiến:** `D:\Learning\Projects\SimpleRAGChatbot\rag_api.py`, `rag_service.py`, `knowledge_loader.py`, `embed_pdfs.py`, `knowledge_base/public/`; controller/service/views mới trong `laravel/app/`, `laravel/resources/views/manager/`; cấu hình `laravel/config/services.php` và `.env.example` của cả hai project.

**Kiểm tra:** test API quản trị không có secret bị từ chối; thêm/sửa/ngừng/xóa tài liệu và hỏi lại thấy câu trả lời/nguồn cập nhật; lỗi lập chỉ mục không làm chatbot mất chỉ mục trước đó; test Laravel giả lập RAG lỗi. Thử toàn luồng trên web với RAG chạy thật.

## Giai đoạn 4 — Tài khoản và quyền truy cập

**Việc làm**

1. Thêm chức năng đổi mật khẩu cho người đang đăng nhập: yêu cầu mật khẩu hiện tại, mật khẩu mới và xác nhận; dùng cơ chế hash sẵn có của model `User`.
2. Thêm `active` cho tài khoản nhân viên. Quản lý có thể khóa/mở; tài khoản bị khóa không đăng nhập lại được và các session đang có không được tiếp tục thao tác. Không cho khóa tài khoản quản lý qua màn nhân viên.
3. Màn danh sách nhân viên hiển thị trạng thái và các quyền hiện có. Lưu thời điểm/người khóa để hỗ trợ tra cứu.

**Vị trí dự kiến:** migration `users`, `laravel/app/Models/User.php`, `laravel/app/Http/Controllers/AuthController.php`, `ManagerEmployeeController.php`, middleware hoặc policy, views tài khoản và routes.

**Kiểm tra:** mật khẩu cũ sai bị từ chối; mật khẩu mới đăng nhập được; nhân viên bị khóa bị chặn cả khi đã có session; quyền của tài khoản khác không đổi.

## Giai đoạn 5 — Sao lưu, bàn giao và nghiệm thu

**Việc làm**

1. Viết một hướng dẫn bàn giao ngắn: cài PHP/Composer/Node/Python/MySQL, tạo `.env` riêng, migrate, build, chạy Laravel và RAG, kiểm tra SMTP, tạo quản lý ban đầu và đổi mật khẩu mẫu.
2. Chuẩn bị lệnh sao lưu/khôi phục MySQL và thư mục upload/banner cùng tài liệu RAG. Lưu bản backup ngoài thư mục `public`; thử khôi phục trên database thử riêng trước khi bàn giao. Không làm nút backup trong web nếu lệnh và hướng dẫn đủ cho phạm vi đồ án.
3. Kiểm tra mọi đường dẫn quản trị và luồng khách/nhân viên sau khi thêm chức năng; thử từ giao diện với các vai trò, không chỉ chạy PHPUnit. Ghi kết quả và các giới hạn còn lại trong test plan.

**Vị trí dự kiến:** `README.md`, `laravel/docs/test-plan.md`, `.env.example` của Laravel và RAG; không đưa `.env` thật hoặc backup vào Git.

**Lệnh xác minh dự kiến:** `php artisan test --compact`, `npm run build`, `python -m unittest discover -s tests -v`; kiểm tra thêm bằng trình duyệt và phục hồi database thử. Chạy test Laravel trên database test riêng, không trên MySQL đang có dữ liệu thật.

## Thứ tự giao việc

Làm giai đoạn 1 → 2 → 3 → 4 → 5, commit sau từng nhóm đã qua test. Riêng trang quản lý tài liệu RAG chỉ đưa vào menu khi API cập nhật chỉ mục an toàn và đã chạy thử với dịch vụ thật. Trước khi bàn giao, xác nhận người nhận có cả hai project hoặc đóng gói chúng cùng hướng dẫn chạy.
