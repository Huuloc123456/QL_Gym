# Kế hoạch tra cứu lịch sử, báo cáo doanh thu và quản lý chatbot

**Mục tiêu:** Người quản lý và nhân viên tìm được dữ liệu cũ khi có hàng trăm bản ghi; quản lý xuất được báo cáo đơn hàng, doanh thu; việc cập nhật chatbot dễ hiểu với người không biết Markdown.

**Trạng thái:** Đã triển khai các mục 1–5 và xác minh bằng kiểm thử tự động. Mục 6 không bổ sung phân trang gói/banner vì hai danh sách này hiện ít dữ liệu; README và test plan đã cập nhật.

**Phạm vi:** Laravel + MySQL hiện có, dịch vụ `SimpleRAGChatbot` hiện có, giao diện xanh/trắng của The Gym. Giữ nguyên dữ liệu đơn, khoản thu, lượt tập và 12 tài liệu RAG hiện tại.

## 1. Đơn hội viên của nhân viên — ưu tiên cao

- Tại `/staff/don-hang`, mặc định hiện **Cần xử lý** (đơn chờ); có tab **Lịch sử** (đã thanh toán, đã hủy) và **Tất cả**.
- Tìm theo mã đơn, tên khách, số điện thoại hoặc email; lọc trạng thái và khoảng ngày tạo đơn. Hiện số kết quả, 20 đơn/trang và giữ bộ lọc khi chuyển trang.
- Đơn trong lịch sử hiện dạng gọn; mở chi tiết để xem các khoản đã nhận, ngày xác nhận và thời hạn gói. Các thao tác nhận tiền/xác nhận/hủy vẫn chỉ xuất hiện khi nghiệp vụ cho phép.
- Truy vấn và phân trang ở MySQL, không tải mọi đơn rồi lọc trong PHP. Sắp xếp mới nhất trước, dùng mã đơn để ổn định thứ tự khi trùng thời điểm.

**File chính đã sửa/thêm:** `laravel/app/Http/Controllers/StaffOrderController.php`, `laravel/app/Repositories/MembershipOrderRepository.php`, `laravel/resources/views/staff/orders/index.blade.php`, `laravel/tests/Feature/StaffOrderBrowseTest.php`.

**Nghiệm thu:** Tạo trên 100 đơn trong database test; tìm đúng mã/khách, xem được đơn cũ ở trang sau, tab lịch sử không lẫn đơn chờ; phân quyền và luồng thanh toán hiện có vẫn qua test.

## 2. Báo cáo đơn hàng và doanh thu — ưu tiên cao

- Thêm `Quản lý → Báo cáo` với khoảng ngày, mặc định tháng hiện tại. Chỉ tài khoản quản lý được xem và tải file.
- Trên màn hình có số đơn tạo trong kỳ theo trạng thái, **doanh thu đơn đã xác nhận** (tổng `membership_orders.amount` của đơn được xác nhận qua `paid_at` trong kỳ), và **tiền thực nhận trong kỳ** (tổng `order_payments.amount` theo `received_at`), tách tiền mặt/chuyển khoản.
- Hai số tiền có thể khác nhau nếu khách trả từng phần ở các kỳ khác nhau; hiển thị nhãn và giải thích ngay trên báo cáo. Ngày đầu/cuối tính theo múi giờ phòng tập, bao gồm trọn ngày cuối.
- Danh sách đơn xuất Excel gồm đơn tạo trong kỳ hoặc được xác nhận trong kỳ để đối chiếu được cả số đơn lẫn doanh thu. Mỗi dòng đơn có mã, khách, gói, ngày tạo/xác nhận, trạng thái, giá trị, đã nhận, còn phải thu; mỗi khoản thu có ngày, phương thức, số tiền và người ghi nhận. File được tạo để tải xuống, không lưu trong thư mục công khai.
- Tên khách/mã tham chiếu trong file phải được xử lý để Excel không thực thi công thức do người dùng nhập. Không gọi số tiền là "lợi nhuận" hoặc "doanh thu ròng" vì hệ thống chưa theo dõi chi phí/hoàn tiền.

**File đã thêm:** controller và lớp truy vấn báo cáo dưới `laravel/app/`, view dưới `laravel/resources/views/manager/reports/`, route/menu quản lý và feature test. Báo cáo dùng PhpSpreadsheet 5.10; runtime PHP cần bật `ext-zip`.

**Nghiệm thu:** Đơn tạo tháng trước nhưng xác nhận tháng này chỉ tính vào doanh thu tháng này; khoản tiền nhận ở tháng khác chỉ tính vào tiền thực nhận tháng đó. Tổng trên màn hình khớp file xuất; nhân viên/khách truy cập báo cáo bị 403; file mở được trong Excel.

## 3. Lịch sử check-in của nhân viên — ưu tiên cao

- Giữ khu vực ghi lượt vào tập ở đầu trang. Bên dưới có **Đang tập** và **Lịch sử**, tìm bằng mã/tên/số điện thoại hội viên, lọc khoảng ngày và trạng thái, 20 lượt/trang.
- Bỏ giới hạn chỉ nhìn thấy 50 lượt gần nhất ở màn nhân viên; vẫn giữ thao tác ghi giờ ra/đóng thủ công cho lượt đang mở, kể cả khi có nhiều lượt mới hơn.

**File chính đã sửa/thêm:** `laravel/app/Http/Controllers/StaffCheckInController.php`, `laravel/app/Repositories/CheckInRepository.php`, `laravel/resources/views/staff/checkins/index.blade.php`, `laravel/tests/Feature/StaffCheckInBrowseTest.php`.

**Nghiệm thu:** Với hơn 50 lượt, nhân viên vẫn tìm được lượt cũ và lượt đang tập; bộ lọc không cho sửa lượt đã đóng; lịch sử của khách khác không lộ qua trang khách hàng.

## 4. Lịch sử của khách hàng — ưu tiên vừa

- `/my/don-hang`: lọc chờ/đã xác nhận/đã hủy và phân trang 20 đơn; chi tiết đơn tiếp tục kiểm tra đúng chủ sở hữu.
- `/my/goi-tap`: tách gói đang dùng, sắp bắt đầu và đã hết hạn; phân trang phần lịch sử. Không đổi quy tắc tính thời hạn gói.

**File chính đã sửa/thêm:** `laravel/app/Http/Controllers/MembershipOrderController.php`, `laravel/app/Http/Controllers/MemberController.php`, `laravel/resources/views/orders/index.blade.php`, `laravel/resources/views/memberships/index.blade.php`, `laravel/resources/views/memberships/_cards.blade.php`, `laravel/tests/Feature/CustomerHistoryTest.php`.

**Nghiệm thu:** Khách có nhiều đơn/gói vẫn xem được bản ghi cũ; đổi ID trên URL không xem được dữ liệu người khác.

## 5. Quản lý nội dung chatbot — ưu tiên vừa

- Danh sách tài liệu gọn hơn: tiêu đề, trạng thái, thời điểm sửa, ô tìm; ẩn mã kỹ thuật và đoạn Markdown thô khỏi màn chính. Giữ 12 tài liệu hướng dẫn hiện có, cho sửa nội dung ở form tiếng Việt dễ hiểu.
- Thêm luồng **Câu hỏi thường gặp**: quản lý nhập Chủ đề, Câu hỏi, Câu trả lời, trạng thái; hệ thống tự tạo mã và Markdown cho RAG. Nội dung dài vẫn có thể quản lý như tài liệu hướng dẫn riêng.
- Thêm **Tải mẫu Excel / Nhập Excel** cho nhiều câu hỏi thường gặp. Mẫu có `Mã` (để trống khi thêm), `Chủ đề`, `Câu hỏi`, `Câu trả lời`, `Trạng thái`. Sau khi tải lên phải xem trước số mục thêm/sửa và lỗi từng dòng, rồi xác nhận; không ghi một phần nếu có dòng lỗi. Giới hạn `.xlsx`, kích thước và số dòng ở server.
- RAG cần thao tác cập nhật hàng loạt và dựng lại chỉ mục **một lần** sau khi xác nhận; nếu dựng lỗi thì khôi phục tập tài liệu và chỉ mục trước đó. Tránh dựng lại 100 lần khi nhập 100 dòng.
- Bỏ khung **Thử chatbot** và endpoint chỉ phục vụ khung đó. Nút nổi **Hỏi The Gym** vẫn dùng để thử; widget hiện đã nhận và hiển thị nguồn trả lời, cần giữ phần này hoạt động khi quản lý hỏi.

**File chính đã sửa/thêm:** `laravel/app/Http/Controllers/ManagerRagKnowledgeController.php`, `laravel/app/Services/RagKnowledgeService.php`, views `laravel/resources/views/manager/knowledge/`, `laravel/routes/web.php`; API batch và kiểm thử trong `D:/Learning/Projects/SimpleRAGChatbot/rag_admin.py`, `tests/test_rag_admin.py`.

**Nghiệm thu:** Người không biết Markdown thêm/sửa được một câu hỏi; Excel hợp lệ thêm/sửa đúng và chatbot dùng nội dung mới; file sai/thiếu cột/trùng mã được báo trước khi lưu; lỗi RAG không làm mất tài liệu cũ; không còn hai giao diện hỏi chatbot trên cùng trang.

## 6. Danh sách nhỏ và hoàn tất

- Gói tập và banner thường ít mục: giữ giao diện hiện tại khi dữ liệu ít; thêm tìm/lọc và phân trang khi danh sách quản lý vượt mức dễ đọc. Trang chủ không nên tải quá nhiều ảnh banner cùng lúc.
- Những nơi đã xử lý ổn gồm đăng ký tập thử (tìm/lọc/phân trang), tài khoản nhân viên (tìm/phân trang), lịch sử tập của khách (phân trang) và dashboard (chỉ hiện 8 đơn gần nhất); không làm lại các màn này nếu không có lỗi cụ thể.
- Chạy test liên quan từng giai đoạn, sau đó toàn bộ test Laravel/RAG, build frontend, kiểm tra bằng tài khoản quản lý/nhân viên/khách trên database QA. Đối chiếu Excel xuất với bản ghi MySQL. Cập nhật README và test plan sau khi nghiệm thu.

**Thứ tự thực hiện:** 1 → 2 → 3 → 4 → 5 → 6. Mỗi giai đoạn chỉ chuyển tiếp khi ca kiểm thử của giai đoạn đó đạt. Không sửa dữ liệu thật để tạo 100 đơn thử; dùng database QA riêng.

## Kết quả triển khai

- Đã hoàn thành mục 1–5: đơn nhân viên có lọc/tìm kiếm/phân trang; báo cáo quản lý có xuất Excel; check-in và lịch sử khách hàng có phân trang; RAG có form FAQ và nhập Excel theo lô.
- Đã chạy 114 kiểm thử Laravel (674 assertions, loại `PasswordRecoveryTest` theo yêu cầu) và 20 kiểm thử RAG; build giao diện và Pint đều đạt. Chi tiết nằm trong [test plan](../../laravel/docs/test-plan.md).
- QA dùng MySQL riêng `qly_phonggym_test`; không reset database ứng dụng. Kiểm thử không gọi Groq thật hoặc dựng FAISS thật, nên cần nghiệm thu live khi chạy dịch vụ RAG.
- Mục 6 giữ nguyên danh sách gói/banner do hiện ít dữ liệu; không bổ sung phân trang hoặc tìm kiếm chưa cần thiết.
