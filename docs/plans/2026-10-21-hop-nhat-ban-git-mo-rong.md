# Kế hoạch hợp nhất bản Git và mở rộng nghiệp vụ

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Đưa các nghiệp vụ mới từ `newrepo/main` vào web Laravel hiện tại mà không mất chức năng đang làm, không nhân đôi dữ liệu, và chạy đúng trên MySQL.

**Architecture:** Một tài khoản web trong `users` liên kết với một định danh khách hoặc nhân viên trong SQL gốc. Giao dịch mới ghi vào một nguồn nghiệp vụ chính; các bảng Laravel cũ chỉ giữ để đọc/đối chiếu lịch sử cho đến khi chuyển đổi được nghiệm thu. Các tính năng mới được ghép theo luồng hoàn chỉnh, mỗi luồng có kiểm thử tự động và thao tác thật trên trình duyệt.

**Tech Stack:** PHP 8.3, Laravel, Blade, Tailwind, MySQL, PHPUnit; Python RAG hiện có chỉ cần kiểm thử hồi quy.

---

## Phạm vi và quyết định đã có

- Mốc đối chiếu: `HEAD` là `51c760f`, `newrepo/main` là `1ec9478`; bản Git nối tiếp `HEAD` một commit. Workspace có thay đổi chưa commit. Phép ghép thử phát hiện **13 file xung đột nội dung**, không sửa workspace.
- Giả định mở rộng **cửa hàng, hóa đơn, PT, lớp học và báo cáo** từ bản Git. Giữ giao diện The Gym hiện tại, banner/cài đặt/RAG, Excel, tra cứu có phân trang, khóa tài khoản và ba quyền nhân viên.
- Dùng **MySQL** cho ứng dụng và test. Không thêm bước OTP đăng nhập vào luồng đang dùng vì người dùng đã chọn bỏ xác minh tài khoản nhiều bước. Giữ khôi phục mật khẩu qua email.
- Thanh toán vẫn là nhân viên ghi nhận tiền mặt/chuyển khoản và xác nhận. Không tự suy diễn giao dịch ngân hàng.
- Không chép `.env`, mật khẩu, backup hay dữ liệu MySQL thật vào Git. Không chạy migration nhập SQL trên database hiện có trước khi phục hồi thử trên bản sao.
- Trước khi làm giao diện cho nghiệp vụ mới, đưa bản xem trước theo tông C# đã duyệt để người dùng xem. Chỉ cần duyệt phần giao diện mới; không thiết kế lại toàn website.

## 1. Chốt hiện trạng và giữ đường quay lại

**Vị trí:** `README.md`, `laravel/docs/test-plan.md`, toàn bộ thay đổi hiện có; không sửa dữ liệu khi làm bước này.

1. `git fetch newrepo main`, kiểm tra `HEAD`, `newrepo/main`, `git status --short`, và đối chiếu lại danh sách file thay đổi. Nếu remote đã có commit mới, lập lại ma trận xung đột trước khi ghép.
2. Rà soát các file chưa commit; tách các thay đổi ứng dụng, test và tài liệu thành commit trên nhánh hiện tại. Không dùng `reset --hard` hoặc ghi đè file. Để riêng file preview không cần đưa vào sản phẩm.
3. Chạy bộ test hiện tại trên database MySQL **test riêng** và ghi số test đạt vào `laravel/docs/test-plan.md`. Chụp danh sách route để so sánh sau ghép: `php artisan route:list --except-vendor`.
4. Sao lưu MySQL ứng dụng gồm schema, dữ liệu, procedure và trigger; sao lưu file tải lên và tài liệu RAG ở nơi riêng tư. Phục hồi thử vào **database thử khác** và xác nhận đọc được dữ liệu trước khi nhập SQL gốc.

**Hoàn thành khi:** Có commit hiện trạng, bản backup khôi phục được, và kết quả test nền; không mất file chưa commit.

## 2. Chốt một mô hình dữ liệu và sửa migration va chạm

**Vị trí:** `laravel/database/migrations/2026_10_21_124721_create_gym_settings_table.php`, `laravel/database/migrations/2026_10_21_124723_add_active_flag_to_users_table.php`, migration `2026_10_21_183807_integrate_original_gym_sql.php` và `2026_10_21_201616_add_portal_completion_features.php` từ Git, `laravel/app/Services/GymIdentityService.php`, `laravel/app/Services/GymPortalService.php`.

1. Viết test migration trên MySQL trống và trên bản sao dữ liệu hiện tại; xác nhận bản Git **đang tạo lại `gym_settings` và thêm lại `users.is_active`**, hai cột/bảng đã có trong workspace. Test phải thất bại ở bản ghép nguyên trạng.
2. Giữ bảng `gym_settings` và các cột `gym_name`, `opening_hours`, `bank_account_name`, `introduction`, `faq_items` đang được màn quản lý dùng. Migration mới chỉ **thêm** `payment_hours`, `reminder_days`, `shipping_fee`, `email_enabled`, `last_processed_at`; chỉnh service mới đọc đúng tên cột thống nhất.
3. Giữ `users.is_active`, `locked_at`, `locked_by` của hiện tại. Migration SQL chỉ thêm `sql_customer_id`, `sql_employee_id` và các thay đổi email/phone cần thiết. Không thêm cột cùng tên lần hai.
4. Kiểm thử liên kết định danh: mỗi `users.id` trỏ đúng một `KHACHHANG.MAKH` hoặc `NHANVIEN.MANV`; chạy lệnh liên kết lại không tạo bản sao. Tài khoản bị khóa vẫn bị từ chối sau khi liên kết.
5. Chốt quy tắc chuyển dữ liệu: đơn/gói/lượt cũ giữ nguyên để tra cứu; đơn mới có một hóa đơn SQL `HOADON` và một mã liên kết; tiền nhận có một bản ghi `gym_receipts`. Không ghi cùng một giao dịch mới vào hai nguồn doanh thu độc lập.

**Hoàn thành khi:** Migration chạy được trên database MySQL mới lẫn bản sao dữ liệu hiện tại; khôi phục và liên kết lại không mất bản ghi hay tạo tài khoản/gói trùng.

## 3. Ghép code nền và xử lý 13 file xung đột

**Vị trí xung đột:** `README.md`; `laravel/app/Http/Controllers/{AuthController,HomeController,MemberController,StaffCheckInController}.php`; `laravel/app/Providers/AppServiceProvider.php`; `laravel/app/Repositories/{CheckInRepository,MembershipRepository}.php`; `laravel/bootstrap/app.php`; `laravel/resources/views/{home,layouts/app,memberships/index,staff/checkins/index}.blade.php`.

1. Sau khi lưu hiện trạng bằng commit, ghép `newrepo/main` trên nhánh hiện tại bằng `git merge --no-commit newrepo/main`. Đọc **cả hai phía** của mỗi khối xung đột; không chọn hàng loạt `ours` hoặc `theirs`.
2. `AuthController` + `bootstrap/app.php`: một luồng đăng nhập dùng hash/session; giữ kiểm tra tài khoản bị khóa, ba vai trò và khôi phục email. Gộp hai middleware kiểm tra tài khoản hoạt động thành một; OTP của Git không xuất hiện trong đăng nhập thường.
3. `AppServiceProvider`: giữ dữ liệu cài đặt/banner và thông báo mới; đồng bộ SQL khi tạo/sửa tài khoản/gói bằng một service, tránh gọi hai lần từ controller và model event. Test trường hợp transaction thất bại không để lại nửa tài khoản.
4. `MemberController` + `MembershipRepository` + `memberships/index`: hiển thị lịch sử Laravel và SQL theo cùng một định danh, phân biệt bản ghi đã liên kết bằng `sql_membership_id`; tính quyền tập theo ngày và trạng thái SQL, không hiện trùng một gói.
5. `StaffCheckInController` + `CheckInRepository` + `staff/checkins/index`: giữ tìm kiếm/lọc/phân trang hiện có, hiển thị lượt đang mở riêng, và nhận diện đúng khách SQL. Quy tắc check-in/check-out vẫn kiểm tra gói còn hiệu lực và chặn thao tác lặp.
6. `HomeController` + hai view chung: giữ banner/cài đặt/FAQ/chatbot và bổ sung sản phẩm/lớp/PT theo giao diện được duyệt. Menu theo vai trò không xuống dòng bất thường và không trỏ tới trang không được phép.
7. `README.md` mô tả **trạng thái thực tế sau ghép** và yêu cầu chạy cả Laravel, MySQL, RAG; bỏ thông tin cổng, tài khoản hoặc tính năng không còn đúng.
8. Kiểm tra thêm mọi file Git tự ghép thành công, nhất là `laravel/routes/web.php`, `User.php`, `MembershipOrderService.php`, `CheckInService.php`: tự ghép văn bản vẫn có thể tạo đường dẫn trùng hoặc thay đổi hành vi.

**Hoàn thành khi:** Không còn dấu conflict, ứng dụng boot được, test nền vẫn đạt; chỉ commit merge sau khi các lỗi schema và route trọng yếu được xử lý.

## 4. Thống nhất đường dẫn, quyền và cấu hình

**Vị trí:** `laravel/routes/web.php`, `laravel/app/Http/Middleware/`, `laravel/app/Policies/`, `laravel/app/Http/Controllers/{ManagerReportController,GymReportController,ManagerSiteSettingController,GymPortalController,GymInvoiceController}.php`.

1. Viết test `route:list`/HTTP cho các URL chính. Chỉ có **một** trang `/quan-ly/bao-cao`; giữ xuất Excel đang có, thêm CSV ở đường dẫn riêng nếu báo cáo mới cần. Tránh hai controller cùng đăng ký GET một URL.
2. Chỉ có một trang cài đặt phòng tập và một luồng đổi mật khẩu; gộp trường mới vào màn hiện có, giữ các URL cũ bằng redirect nếu cần.
3. Giữ ba cờ quyền nhân viên cho tập thử, xử lý đơn/thu tiền và check-in. Các endpoint hóa đơn, duyệt tập thử, giao nhận mới phải dùng chính các quyền đó; manager có quyền quản trị. Khách chỉ xem hóa đơn của chính mình.
4. Tách rõ quản lý tài khoản **web** và quản lý tài khoản **MySQL**; chỉ mở chức năng MySQL cho manager trên kết nối quyền giới hạn, không cho thao tác với root hoặc tài khoản ứng dụng.

**Hoàn thành khi:** Kiểm tra trực tiếp URL cho khách, nhân viên từng tổ hợp quyền và manager; thao tác trái quyền trả 403, trang hợp lệ vào được.

## 5. Ghép nghiệp vụ theo từng luồng hoàn chỉnh

**5A — Gói, tập thử, check-in.** Tại `MemberController.php`, `TrialRequestController.php`, `MembershipRepository.php`, `CheckInService.php`, các view tương ứng: test gói SQL và gói lịch sử, ngày hết hạn, tập thử được duyệt, check-in/check-out lặp và hội viên không sở hữu gói. Chỉ bật luồng sau khi MySQL test đạt.

**5B — Đơn và hóa đơn.** Tại `MembershipOrderService.php`, `GymCommerceService.php`, `GymInvoiceController.php`, `gym_receipts`: ghi khoản thu tiền mặt/chuyển khoản, thu một phần, xác nhận/hủy và tạo quyền tập đúng một lần. Hóa đơn sản phẩm, gói, lớp, PT cùng dùng một quy tắc trạng thái tiền. Test gửi xác nhận lặp và hai request đồng thời.

**5C — Cửa hàng và tồn kho.** Tại `GymStoreController.php`, `GymCommerceService.php` và các bảng `SANPHAM`, `CHITIETHOADON`: tìm kiếm, giỏ hàng, nhận tại quầy/giao hàng, giữ hàng và hoàn tất. Trigger SQL trừ tồn đúng một lần khi chốt; hủy đơn chưa chốt giải phóng phần giữ; không cho tồn âm. Bài test phải đối chiếu số lượng trước/sau ở MySQL.

**5D — PT và lớp.** Tại `GymTrainingController.php`, `GymTrainingService.php`: test số buổi, sĩ số, lịch trùng PT/học viên, đổi/hủy lịch và quyền xem lịch. Không xem lịch sử lớp năm 2025 là lịch còn hiệu lực.

**5E — Báo cáo.** Tại `ManagerReportService.php`, `GymReportController.php`, `laravel/resources/views/manager/reports/index.blade.php`: một trang báo cáo tách **doanh thu theo hóa đơn** và **thực thu theo ngày nhận tiền**, cho lọc theo gói/sản phẩm/PT/lớp. Khi kết hợp lịch sử Laravel và SQL, dùng mã liên kết để không cộng đôi. Giữ file Excel; CSV có thể là định dạng phụ. Test bằng đơn thu một phần, đơn hủy, đơn hỗn hợp và cùng khoản thu xuất hiện ở hai bảng liên kết.

**Hoàn thành từng luồng khi:** Test trên MySQL riêng đạt, nhân viên/khách/manager thao tác được trên trình duyệt, dữ liệu còn đúng sau tải lại và không làm hỏng luồng đã nghiệm thu.

## 6. Kiểm thử hồi quy và bàn giao

1. Chạy các test Laravel liên quan sau mỗi luồng; cuối cùng chạy `php artisan test --compact` trên MySQL test, `vendor/bin/pint --test`, `npm run build`; test RAG bằng `python -m unittest discover -s tests -v` trong project RAG.
2. Thao tác tay từ trình duyệt với ba vai trò: đăng nhập/khóa tài khoản; xem gói, mua và thanh toán; thử tập và check-in; mua sản phẩm và đối chiếu tồn; đăng ký PT/lớp; xem hóa đơn và xuất Excel; sửa banner/cài đặt/tài liệu RAG. Kiểm tra 403 bằng URL trực tiếp và thử tải lại để xác nhận dữ liệu đã lưu.
3. Trên database thử đã phục hồi, so sánh số lượng tài khoản, đơn, gói, lượt tập trước/sau chuyển đổi; chọn vài bản ghi cụ thể đối chiếu cả web và SQL. Ghi sai lệch cùng cách xử lý vào `laravel/docs/test-plan.md`.
4. Cập nhật `README.md`, `.env.example` và hướng dẫn MySQL/migration/SMTP/RAG. Commit theo nhóm đã qua kiểm thử; chỉ đẩy lên remote sau khi phiên bản tích hợp được xem lại.

**Điều kiện kết thúc:** Toàn bộ luồng mới và cũ được kiểm thử, không còn route/schema trùng, không mất dữ liệu lịch sử, báo cáo không tính trùng tiền, và bản sao dữ liệu thật chuyển đổi thử thành công.
