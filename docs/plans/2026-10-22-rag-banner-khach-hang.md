# Kế hoạch cải thiện RAG, banner và quản lý khách hàng

**Mục tiêu:** Chatbot trả lời sát dữ liệu đang bán, gửi bằng Enter, lưu tài liệu gốc trong MySQL; banner tự chuyển; quản lý tra cứu và hỗ trợ khách hàng trên một màn riêng.

**Kiến trúc:** Laravel/MySQL giữ tài liệu và dữ liệu khách/gói làm nguồn gốc. Python dựng FAISS từ tài liệu đang bật; chỉ mục là dữ liệu dẫn xuất, có thể tạo lại. Màn khách hàng mới dùng KHACHHANG và liên kết users.sql_customer_id hiện có, không tạo tập khách thứ hai.

**Công nghệ:** Laravel, Blade/Tailwind, JavaScript, MySQL; Python, LangChain, FAISS, Groq. Giữ tông xanh/trắng của The Gym.

## Hiện trạng

- Chatbot dùng textarea trong laravel/resources/views/components/chatbot.blade.php; laravel/resources/js/app.js chỉ nghe submit, nên Enter xuống dòng.
- Dịch vụ SimpleRAGChatbot/rag_service.py lấy ba đoạn FAISS gần nhất mà chưa có ngưỡng liên quan. Câu hỏi gói hai tháng dễ lấy sai nguồn và suy diễn.
- Quản lý tài liệu qua Laravel nhưng bản gốc là file Markdown trong SimpleRAGChatbot/knowledge_base; MySQL chưa giữ bản gốc.
- Banner đã tự chuyển sau năm giây trong laravel/resources/js/carousel.js, nhưng có nút tạm dừng ở laravel/resources/views/layouts/app.blade.php.
- Danh mục customers hiện tạo/sửa khách và tài khoản được, nhưng bảng chung chỉ tìm theo tên; chưa có màn chi tiết lịch sử mua, gói và tập.

## 1. Gửi câu hỏi bằng Enter

**File:** laravel/resources/js/app.js, resources/views/components/chatbot.blade.php.

1. Trong ô chat: Enter gửi; Shift+Enter xuống dòng; không chặn Enter khi bộ gõ tiếng Việt đang ghép chữ; không gửi trùng khi chờ phản hồi.
2. Giữ nút gửi và thêm dòng hướng dẫn phím ngắn gọn.
3. Kiểm tra trực tiếp Enter, Shift+Enter, gõ tiếng Việt có dấu, bấm liên tiếp và trên điện thoại.

## 2. RAG trả lời đúng hơn

**File:** D:/Learning/Projects/SimpleRAGChatbot/rag_service.py, knowledge_loader.py, tests/; Laravel app/Http/Controllers/ChatbotController.php và endpoint dữ liệu công khai mới.

1. Lập bộ câu hỏi nghiệm thu gồm giá/thời hạn gói đang bán, câu hỏi “tập hai tháng bao nhiêu” khi chỉ có gói một tháng, thanh toán, check-in, PT/lớp, hoàn tiền, câu không có nguồn, lỗi gõ và câu nối tiếp. Ghi đáp án và nguồn mong đợi.
2. Lấy giá và gói đang mở bán từ GOITAP qua Laravel thay vì dùng giá cũ trong tài liệu tĩnh. Với yêu cầu hai tháng nhưng không có gói hai tháng, tính giá tham khảo của hai kỳ gói một tháng nối tiếp nhau: giá một tháng hiện tại nhân hai. Nói rõ đây là hai lần mua/gia hạn, chưa tính ưu đãi theo hạng khách hoặc thay đổi giá về sau, không phải giá một gói hai tháng được niêm yết. Nếu có gói hai tháng đang bán, ưu tiên báo giá gói đó. Chỉ tính khi đầu vào đủ rõ và không tự bịa khuyến mãi.
3. Chuẩn hóa câu hỏi tiếng Việt; ưu tiên FAQ khớp rõ; tìm nhiều ứng viên rồi lọc theo mức liên quan/chủ đề. Khi không đủ nguồn, nói chưa có thông tin và không gắn nguồn không được dùng.
4. Chỉ giữ vài lượt chat gần nhất để hiểu câu hỏi nối tiếp trong phiên; giới hạn độ dài và không đưa mật khẩu, OTP hay dữ liệu hội viên vào ngữ cảnh. Đo thời gian phản hồi trước/sau.
5. Chạy bộ câu hỏi bằng dữ liệu giả lập, sau đó thử trực tiếp qua Python /chat và Laravel /chatbot/message.

## 3. Lưu tài liệu RAG vào MySQL

**File Laravel:** migration mới trong laravel/database/migrations, model RagDocument, app/Http/Controllers/ManagerRagKnowledgeController.php, app/Services/RagKnowledgeService.php, resources/views/manager/knowledge/, routes/web.php. **File Python:** rag_admin.py, knowledge_loader.py, rag_api.py, tests/.

1. Thêm bảng rag_documents: mã duy nhất, loại FAQ/hướng dẫn, tiêu đề, câu hỏi, nội dung, trạng thái, phiên bản, người cập nhật, thời điểm cập nhật. Chỉ nhập nội dung hướng dẫn công khai.
2. Sao lưu rồi nhập tài liệu Markdown đang có vào MySQL một lần; đối chiếu mã/số lượng, tránh nhập trùng. Chỉ bỏ đường đọc file cũ sau nghiệm thu.
3. Chuyển tạo/sửa/bật/tắt/xóa và nhập Excel trên màn quản lý sang đọc/ghi MySQL qua Laravel, giữ phân quyền quản lý và validation hiện có.
4. Python nhận bản chụp tài liệu đang bật qua endpoint nội bộ có token của Laravel, dựng FAISS theo phiên bản; khi khởi động hoặc phiên bản đổi thì nạp lại. Trình duyệt không nhận token hay kết nối MySQL.
5. Lưu dữ liệu gốc trước; dựng chỉ mục mới tạm thời và chỉ thay chỉ mục đang dùng khi thành công. Nếu thất bại, giữ chỉ mục cũ, hiển thị “Chưa đồng bộ” và cho thử lại; không báo đã học thành công.
6. Kiểm tra CRUD, nhập Excel, lỗi API, khởi động lại Python, nội dung trùng, tài liệu tạm dừng và quyền truy cập.

## 4. Banner tự chuyển

**File:** laravel/resources/views/layouts/app.blade.php, resources/js/carousel.js.

1. Bỏ nút “Tạm dừng/Chạy tự động” và logic của nút. Banner tự chuyển mỗi năm giây; các nút chọn ảnh vẫn dùng được.
2. Tạm ngưng khi người dùng đang di chuột hoặc thao tác bàn phím trên banner, chạy tiếp khi rời đi; tôn trọng cài đặt giảm chuyển động.
3. Kiểm tra một banner, nhiều banner và banner do quản lý cập nhật.

## 5. Quản lý khách hàng

**File:** controller mới laravel/app/Http/Controllers/ManagerCustomerController.php, view mới laravel/resources/views/manager/customers/, routes/web.php, resources/views/layouts/internal-navigation.blade.php. Tái sử dụng GymIdentityService, KHACHHANG, users và các bảng giao dịch hiện có.

1. Làm bản xem trước danh sách và chi tiết cùng tông hiện tại để người dùng duyệt trước khi triển khai UI.
2. Danh sách 20–25 khách/trang: tìm mã, tên, điện thoại, email; lọc trạng thái tài khoản, hạng và gói; hiển thị liên hệ, gói hiện tại, lần tập gần nhất. Truy vấn có phân trang, tránh truy vấn riêng cho từng khách.
3. Chi tiết một khách: hồ sơ/tài khoản, gói và lịch sử, hóa đơn, check-in, PT/lớp. Từng lịch sử có phân trang hoặc liên kết sang màn hiện có; không viết luồng thanh toán/check-in thứ hai.
4. Cho quản lý sửa trường hợp lệ và khóa/mở tài khoản theo quy tắc hiện có; giữ mã khách, tên đăng nhập; kiểm tra trùng email/điện thoại. Không xóa khách đã có giao dịch. Chuyển URL danh mục khách cũ sang trang mới hoặc giữ tương thích.
5. Chỉ quản lý xem toàn bộ khách; khách vẫn chỉ xem tài khoản mình qua /my; nhân viên dùng màn vận hành hiện có. Thử URL trực tiếp và đổi ID bằng tài khoản khác vai trò.

## Thứ tự và nghiệm thu

1. Duyệt bản xem trước trang khách. Sửa Enter và banner, kiểm tra trên web.
2. Chốt bộ câu hỏi RAG và nguồn giá gói trực tiếp; sau đó chuyển kho tài liệu sang MySQL và dựng lại chỉ mục.
3. Nâng truy xuất RAG, làm trang khách, kiểm thử tự động và thao tác tay trên bản QA với ba vai trò.
4. Cập nhật README và hướng dẫn vận hành khi các tính năng đạt.

**Đạt khi:** Enter gửi đúng; banner tự chạy mà không có nút tạm dừng; tài liệu MySQL vẫn dùng được sau khi khởi động lại RAG; câu hỏi mẫu trả lời đúng hoặc từ chối khi thiếu nguồn; quản lý tìm/xem được lịch sử khách trong danh sách lớn; vai trò khác không xem trái phép.
