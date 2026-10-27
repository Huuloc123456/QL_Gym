# Hoàn thiện quy trình nghiệp vụ The Gym — Implementation Plan

> **For Codex:** REQUIRED SUB-SKILL: Use @executing-plans to implement this plan task-by-task. Áp dụng @systematic-debugging và @test-driven-development khi sửa lỗi, cùng hướng dẫn kiểm thử trong `laravel/.agents/skills/testing-best-practices/SKILL.md`.

**Goal:** Mỗi yêu cầu tập thử, đơn hàng, khoản thu và quyền tập đều có chủ sở hữu, trạng thái, người xử lý và một kết quả cuối rõ ràng; không còn tạo tài khoản ngầm hoặc nhận tiền rồi không có đường xử lý.

**Architecture:** Giữ Laravel/Blade/MySQL hiện có và các transaction đang bảo vệ thao tác ghi. Đơn mới dùng hóa đơn làm nguồn giao dịch; đơn gói cũ được giữ để tra cứu và xử lý tiếp, không tạo thêm một luồng bán mới. Tập thử dùng quyền thử riêng theo ngày; hoàn tiền dùng chứng từ bổ sung liên kết khoản thu gốc, không sửa/xóa lịch sử thu tiền.

**Tech Stack:** Laravel 13, PHP 8.3+, MySQL, Blade/Tailwind, PHPUnit, JavaScript hiện có, dịch vụ RAG riêng và VNPay sandbox khi có cấu hình. Không thêm dependency cho kế hoạch này.

**Trạng thái (27/10/2026):** Các thay đổi của Task 0–8 đã được triển khai; Task 9 đã chạy hồi quy tự động và cập nhật tài liệu. Kiểm tra trực tiếp trong ChatGPT xác nhận trang quản lý, nhân viên và khách vãng lai. Chưa gọi là khép kín hoàn toàn: đăng nhập hội viên mới chưa làm trên trình duyệt, dịch vụ RAG không chạy, scheduler chưa được bật và chưa có giao dịch VNPay sandbox/IPN thật. Không push hoặc merge trong lượt này.

---

## 1. Phạm vi và các quyết định

| Nội dung | Hướng thực hiện |
| --- | --- |
| Tập thử | Khách đăng nhập hoặc tự tạo tài khoản trước khi gửi; nhân viên duyệt mới có quyền tập. |
| Chủ sở hữu dữ liệu | Lấy tài khoản/hồ sơ từ phiên đăng nhập; không nhận `user_id` hoặc danh tính khác do trình duyệt gán. |
| Tài khoản | Dùng lại tài khoản hội viên hiện có; bỏ việc tự tạo tài khoản với mật khẩu khách không biết khi duyệt thử. |
| Thời lượng thử | Tách khỏi `GOITAP.THOIHAN` của gói trả phí; không gọi procedure đang hiểu thời hạn tháng thành ngày. |
| Đơn mới | Qua một luồng hóa đơn/checkout; các URL đơn gói cũ phục vụ lịch sử và xử lý bản ghi có sẵn. |
| Hoàn tiền | Quản lý ghi nhận tiền đã hoàn bên ngoài sau khi đối chiếu chứng từ; số tiền hoàn không vượt khoản thực nhận chưa hoàn. |
| VNPay đến muộn | Đơn còn chờ được chốt lại ngày hợp lệ; đơn đã hủy được hoàn tiền có chứng từ, không tự hồi sinh. |
| Giỏ hàng | Món không còn bán vẫn được hiển thị để bỏ khỏi giỏ; dữ liệu không hợp lệ chặn checkout với lý do cụ thể. |
| Lớp học | Kiểm tra lịch trùng cả giữa các lớp trong cùng giỏ trước khi tạo hóa đơn và trước khi xác nhận. |
| Lịch sử | Giữ hóa đơn, khoản thu, chứng từ hoàn, người thao tác và lý do; không xóa để làm cân số liệu. |

### Giả định để triển khai

- Một cơ sở phòng tập; giữ các vai trò customer, employee, manager. PT không thuộc phạm vi.
- Mặc định thử **một ngày lịch**, từ 00:00 ngày được duyệt đến trước 00:00 ngày tiếp theo theo múi giờ phòng tập. Quản lý có thể cấu hình 1–7 ngày; nhân viên không tự đặt thời lượng tùy ý.
- Một quyền thử cho một tài khoản và số điện thoại đã được nhân viên xác minh. Từ chối/hủy trước khi được cấp quyền không tự tính là đã sử dụng; đã cấp quyền thì điều chỉnh trên quyền đó, không tạo quyền thử thứ hai.
- Không thêm OTP/xác minh email bắt buộc. Vì vậy không tuyên bố có thể ngăn tuyệt đối một người dùng nhiều danh tính; nhân viên vẫn phải đối chiếu thực tế trước khi cấp thử.
- Hội viên có gói đã thanh toán đang hiệu lực hoặc sắp hiệu lực, tài khoản bị khóa, hoặc đã được cấp quyền thử trước đó không được tự nhận thêm thử.
- Hoàn tiền trong kế hoạch tập trung vào đơn chưa hoàn tất/cung cấp dịch vụ và khoản VNPay cần đối soát. Đổi trả hàng đã nhận, bảo lưu gói, chuyển nhượng gói và hoàn một phần dịch vụ đã dùng cần chính sách riêng, không tự động suy diễn trong luồng hủy đơn.

### Yêu cầu vận hành

- Phân trang các danh sách lớn; lọc tại database, nạp quan hệ theo lô; không đưa toàn bộ lịch sử khách lên một trang.
- Mọi hành động nhạy cảm kiểm tra vai trò, quyền nhân viên, chủ sở hữu, trạng thái mới nhất và tài khoản đang hoạt động ở server.
- Duyệt thử, thu tiền, hoàn tiền, chốt/hủy đơn và check-in phải an toàn khi gửi lặp hoặc hai nhân viên thao tác đồng thời.
- Bảo toàn dữ liệu MySQL đang chạy; migration cộng thêm, có báo cáo đối chiếu, không `migrate:fresh` hoặc seed lại database ứng dụng.
- Tác vụ nền có thời điểm chạy thành công gần nhất và thông báo lỗi; mất scheduler không được làm nhân viên hiểu đơn đã tự xử lý xong.
- Chưa có khóa VNPay/IPN HTTPS là một điều kiện nghiệm thu còn thiếu, không được ghi nhận như giao dịch sandbox đã đạt.

## 2. Quy trình đích

### Tập thử

1. Khách chọn Đăng ký tập thử. Nếu chưa đăng nhập, màn này có Đăng nhập và Tạo tài khoản; sau thành công quay lại đúng form, không tự gửi yêu cầu.
2. Hội viên xem thông tin liên hệ từ hồ sơ, chọn ngày muốn đến và gửi yêu cầu. Sửa hồ sơ qua màn hiện có thay vì nhập một danh tính khác trong form thử.
3. Khách thấy Chờ duyệt; nhân viên thấy yêu cầu mới, tra cứu lịch sử và xác minh khách/số điện thoại.
4. Nhân viên duyệt ngày và thời lượng được phép hoặc từ chối có lý do. Chỉ duyệt mới cấp quyền thử; liên hệ khách không đồng nghĩa đã duyệt.
5. Hội viên thấy Đã duyệt, ngày bắt đầu/ngày cuối và hướng dẫn đến quầy. Nhân viên check-in/check-out qua cùng màn hiện có, ghi rõ nguồn quyền là Tập thử.
6. Hết thời gian thì quyền thử hết hiệu lực; lịch sử vẫn còn. Hội viên mua gói bằng chính tài khoản đó qua checkout thông thường.

Trạng thái yêu cầu: `pending → approved | rejected | cancelled`; liên hệ là thông tin xử lý riêng. Trạng thái quyền thử: `scheduled → active → expired`, hoặc `revoked` do người có quyền và lý do. `active`/`expired` được kiểm tra từ mốc thời gian ngay trong request, không phụ thuộc duy nhất vào scheduler.

### Tiền và đơn hàng

- Đơn chưa nhận tiền: có thể hủy hợp lệ hoặc hết hạn; giải phóng hàng/chỗ.
- Đơn đã nhận một phần tiền: tiếp tục thu phần thiếu hoặc quản lý ghi nhận hoàn khoản đã nhận, sau đó hủy. Không tự hết hạn làm mất dấu tiền.
- Đơn đã nhận đủ nhưng chưa chốt được: hiển thị lý do và bước xử lý; không yêu cầu khách trả thêm hoặc tạo gói/trừ kho một phần.
- Giao dịch VNPay đã thành công nhưng cần đối soát: quản lý chốt lại điều kiện hợp lệ cho đơn còn chờ, hoặc ghi nhận hoàn tiền. Callback lặp không hoàn/chốt lần hai.
- Báo cáo phân biệt tiền vào, tiền đã hoàn, tiền thu ròng, doanh thu dịch vụ đã xác nhận và tiền đang đối soát; không gọi tất cả là doanh thu.

## 3. Task 0 — Chốt dữ liệu nền và bộ test đang lỗi

**Files:** `laravel/phpunit.xml`, `laravel/docs/test-plan.md`; đọc các test đang lỗi trước khi sửa.

1. Kiểm tra branch/worktree, thay đổi đang có và database cấu hình cho test. Sao lưu MySQL cùng file tải lên trước khi áp dụng migration thực tế.
2. Chạy `php artisan test --compact` ở `laravel/`, lưu kết quả mới theo ngày. Con số 198 đạt/12 thất bại/1 ngoại lệ/1 bỏ qua ngày 22/10 chỉ là mốc lịch sử.
3. Phân loại từng lỗi: hành vi nghiệp vụ sai, dữ liệu test thiếu, mock sai ranh giới hoặc kỳ vọng giao diện/redirect đã đổi. Không xóa test hay nới assertion chỉ để xanh.
4. Đọc/reproduce các nhóm `ManagerRagImportTest`, `ManagerRagKnowledgeTest`, `RagProductCatalogTest`, `StaffMembershipTest`; ghi lý do cụ thể và kiểm tra hành vi mới mong đợi.
5. Chỉ cập nhật test khi hợp đồng hành vi đã được chứng minh; các lỗi thực tế đi cùng task sửa tương ứng. Mỗi nhóm thay đổi có commit riêng khi đã đạt.

**Nghiệm thu:** Có danh sách lỗi tái hiện được, database test riêng và bản sao lưu; không đưa lỗi cũ vào nhóm bỏ qua mà không giải thích.

## 4. Task 1 — Tập thử gắn với tài khoản và dữ liệu theo ngày

**Modify:**
- `laravel/app/Http/Controllers/AuthController.php`
- `laravel/app/Http/Controllers/TrialRequestController.php`
- `laravel/app/Models/TrialRequest.php`
- `laravel/app/Repositories/TrialRequestRepository.php`
- `laravel/app/Policies/TrialRequestPolicy.php`
- `laravel/resources/views/home.blade.php`
- `laravel/resources/views/auth/login.blade.php`
- `laravel/resources/views/auth/register.blade.php`
- `laravel/resources/views/layouts/public-navigation.blade.php`
- `laravel/routes/web.php`

**Create:**
- `laravel/app/Services/TrialRegistrationService.php`
- `laravel/app/Models/TrialEntitlement.php`
- `laravel/resources/views/trials/create.blade.php`
- `laravel/resources/views/trials/index.blade.php`
- Các migration thêm dữ liệu chủ sở hữu/trạng thái cho `trial_requests`, tạo `trial_entitlements`, thêm cấu hình thời lượng thử vào `gym_settings`.
- `laravel/tests/Feature/TrialRegistrationWorkflowTest.php`

**Thiết kế dữ liệu:**
- Yêu cầu giữ bản chụp họ tên/điện thoại/email và thêm `user_id`, ngày mong muốn, trạng thái mới, lý do từ chối/hủy, người và thời điểm quyết định. Theo dõi đã liên hệ tách khỏi trạng thái duyệt.
- Quyền thử giữ `trial_request_id` duy nhất, `user_id`, số điện thoại chuẩn hóa tại lúc xác minh, `starts_at`, `ends_at`, người duyệt, thời điểm duyệt/thu hồi và lý do. Ràng buộc chống cấp trùng tài khoản/điện thoại ở database kết hợp transaction.
- Tạo factory đi kèm model mới. Không cấp quyền qua `GOITAP` trả phí hoặc gọi `SP_DANGKYTAPTHU` trong luồng mới.
- Chuẩn hóa số điện thoại nhất quán; đổi điện thoại sau khi gửi yêu cầu buộc nhân viên đối chiếu lại, không chuyển quyền sang một người khác theo chuỗi điện thoại nhập vào.
- Dữ liệu yêu cầu cũ không có chủ sở hữu được giữ và đánh dấu cần đối chiếu. Không tự gắn quyền cho tài khoản chỉ vì trùng số điện thoại/email chưa xác minh.

**Steps:**
1. Viết test guest bị chuyển sang đăng nhập, đăng ký mới quay lại form, customer gửi thành công và không giả mạo được chủ yêu cầu; chạy để thấy lỗi đúng.
2. Thêm migration/model/service tối thiểu; form gửi lấy danh tính từ phiên, kiểm tra tài khoản active và yêu cầu/quyền đã có.
3. Giữ đích quay lại bằng named route/giá trị cho phép trong session; không nhận URL tùy ý và không tự replay POST sau login/register.
4. Thêm màn hội viên xem trạng thái và hủy yêu cầu còn chờ; người khác không đọc/sửa được bằng cách đổi ID.
5. Cập nhật phần trang chủ: guest thấy Đăng nhập/Tạo tài khoản, customer thấy hành động phù hợp với yêu cầu hiện tại, staff/manager không tự gửi yêu cầu hội viên.
6. Chạy `php artisan test --compact --filter='TrialRegistrationWorkflowTest|CustomerAccountTest|LoginPageTest|StaffTrialRequestsTest'`; yêu cầu tất cả ca trong phạm vi đạt rồi commit.

## 5. Task 2 — Duyệt thử, check-in và chuyển thành hội viên trả phí

**Modify:**
- `laravel/app/Services/TrialRegistrationService.php`
- `laravel/app/Services/CheckInService.php`
- `laravel/app/Services/GymIdentityService.php`
- `laravel/app/Repositories/CheckInRepository.php`
- `laravel/app/Repositories/MembershipRepository.php`
- `laravel/app/Models/CheckIn.php`
- `laravel/app/Http/Controllers/TrialRequestController.php`
- `laravel/resources/views/staff/trial-requests/index.blade.php`
- `laravel/resources/views/staff/checkins/index.blade.php`
- `laravel/resources/views/memberships/attendance.blade.php`
- Các màn hồ sơ khách/quản lý cấu hình có liên quan.

**Create:** migration thêm `trial_entitlement_id` nullable cho `check_ins`; `laravel/tests/Feature/TrialAttendanceWorkflowTest.php`.

**Steps:**
1. Viết ca duyệt lặp/hai nhân viên, thời lượng không hợp lệ, chưa xác minh, tài khoản khóa, khách đã có gói/quyền thử và sở hữu sai; chạy test trước sửa.
2. Duyệt trong transaction đang dùng; xác minh trạng thái mới nhất, tạo đúng một quyền thử, lưu người thao tác và thông báo cho khách. Bỏ tạo tài khoản ngầm/mật khẩu ngẫu nhiên trong approval.
3. Phân biệt đánh dấu đã liên hệ, duyệt, từ chối, hủy và đổi lịch trước khi dùng. Không dùng chung nút hoặc cùng một trạng thái cho mọi hành động.
4. Check-in chỉ chọn quyền hợp lệ của tài khoản đang hoạt động: gói trả phí hợp lệ hoặc quyền thử hợp lệ. Lưu nguồn quyền, chặn lượt đang mở, giữ checkout/đóng thủ công có lý do qua màn hiện có.
5. Xử lý quyền thử cũ chỉ từ liên kết rõ `trial_requests.sql_membership_id`: đối chiếu người sở hữu/ngày thực tế, giữ lịch sử và ngăn fallback SQL coi quyền thử đó như gói trả phí. Bản ghi mơ hồ đưa vào danh sách đối chiếu, không tự cấp quyền mới.
6. Hiển thị mốc thời gian đúng, hết quyền ngay tại ranh giới `ends_at`, có nút mua gói nhưng không tự tạo đơn hay ép trả tiền.
7. Chạy `php artisan test --compact --filter='TrialRegistrationWorkflowTest|TrialAttendanceWorkflowTest|StaffCheckInTest|StaffCheckInBrowseTest|CustomerMembershipTest'`; commit khi đạt.

**Nghiệm thu:** Một tài khoản thực hiện được yêu cầu → duyệt → check-in → checkout → hết hạn → mua gói trả phí; không tạo thêm tài khoản và không ghi doanh thu từ quyền thử miễn phí.

## 6. Task 3 — Thống nhất đầu vào đơn mới, giữ lịch sử đơn cũ

**Modify:** `laravel/app/Http/Controllers/MembershipOrderController.php`, `laravel/app/Http/Controllers/GymStoreController.php`, `laravel/app/Services/GymCommerceService.php`, `laravel/routes/web.php`, các form mua gói và các test tương ứng.

1. Viết test cả nút mua gói lẫn POST URL đơn gói cũ: không được tạo hai loại đơn chờ khác nhau cho cùng một ý định mua.
2. Đơn mới dùng checkout/hóa đơn chuẩn có giá chụp, ngày bắt đầu, hạn thanh toán, QR, hủy, đối soát và tra cứu chung. URL tạo đơn cũ đưa khách tới bước xem lại mua gói chuẩn, không tạo một bản `membership_orders` chờ riêng hoặc vô tình đặt luôn các món khác trong giỏ.
3. Giữ màn đọc và thao tác nhân viên cho đơn cũ đã tồn tại; không tự chuyển dữ liệu đơn cũ thành hóa đơn mới, không nhân đôi doanh thu/quyền tập.
4. Kiểm tra danh sách khách, nhân viên và báo cáo không đếm bản mirror của hóa đơn như một đơn bán độc lập.
5. Chạy `php artisan test --compact --filter='UnifiedInvoiceBrowseTest|StaffMembershipTest|CustomerHistoryTest|GymCommerceTest|ManagerReportsTest'`; commit sau khi đạt.

## 7. Task 4 — Hoàn tiền thủ công có chứng từ và đóng đơn đã thu một phần

**Create:**
- `laravel/app/Models/GymRefund.php`, factory và migration tạo `gym_refunds`.
- `laravel/app/Services/GymRefundService.php`
- `laravel/app/Http/Controllers/ManagerRefundController.php`
- `laravel/resources/views/manager/refunds/index.blade.php`
- `laravel/tests/Feature/ManualRefundWorkflowTest.php`

**Modify:** `GymCommerceService.php`, `MembershipOrderService.php`, `MembershipOrder.php`, `ManagerReportService.php`, `GymReportController.php`, `GymInvoiceController.php`, `routes/web.php`, các view hóa đơn/đơn/báo cáo và điều hướng quản lý.

**Hợp đồng tiền:**
- Mỗi chứng từ hoàn liên kết khoản thu gốc (`gym_receipts`, `order_payments` hoặc giao dịch VNPay), đơn đích, số tiền, phương thức, chứng từ ngoài ngân hàng, lý do, người xử lý, thời điểm và token chống gửi lặp.
- Khoản thu gốc là dữ kiện lịch sử. Tách tổng đã thu, đã hoàn và còn giữ; không sửa khoản thu thành số âm, xóa khoản thu hoặc thay thời điểm nhận tiền ban đầu.
- Kiểm tra dưới khóa: số hoàn dương, không vượt phần tiền nguồn chưa hoàn, đúng đơn, đúng phạm vi được phép, chưa giao hàng/cung cấp dịch vụ nếu dùng luồng hủy trước cung cấp.
- Có thể hủy đơn chờ sau khi tiền còn giữ bằng 0 và không còn giao dịch đối soát chưa xử lý. Hàng/chỗ chỉ giải phóng một lần; hủy không tạo một lần hoàn mới.
- Báo cáo và Excel có tiền vào/tiền hoàn/thu ròng theo ngày thực tế, không làm thay đổi kỳ đã ghi nhận khoản thu cũ. Khách thấy số đã hoàn và tình trạng cuối, không được nhắc trả lại toàn bộ đơn đã hủy.

**Steps:**
1. Viết test trả thiếu tiền rồi hủy, hoàn lặp, hoàn vượt nguồn, khác đơn, employee/customer cố hoàn và hai yêu cầu hoàn đồng thời.
2. Thêm ledger/service, kiểm tra trạng thái và idempotency; manager ghi nhận sau khi hoàn ngoài hệ thống, bắt buộc chứng từ/lý do.
3. Cập nhật kiểm tra hủy và hiển thị tiền; giữ quy tắc an toàn cho đơn đã giao/đã dùng, có thông báo chuyển quản lý thay vì hủy trực tiếp.
4. Tích hợp báo cáo/Excel và chữ ký chứng từ: giữ bản hóa đơn đã ký, chứng từ hoàn là bản bổ sung liên kết, không sửa nội dung đã ký.
5. Chạy `php artisan test --compact --filter='ManualRefundWorkflowTest|GymCommerceTest|StaffMembershipTest|ManagerReportsTest|GymPortalCompletionTest'`; commit khi đạt.

## 8. Task 5 — Đối soát VNPay chốt được ngày mới hoặc hoàn tiền rõ ràng

**Modify:** `laravel/app/Services/VnPayService.php`, `laravel/app/Services/GymCommerceService.php`, `laravel/app/Services/MembershipOrderService.php`, `laravel/app/Http/Controllers/ManagerPaymentReviewController.php`, `laravel/resources/views/manager/payment-reviews/index.blade.php`, `laravel/tests/Feature/PaymentReviewWorkflowTest.php`, `laravel/tests/Feature/VnPayPaymentFlowTest.php`.

1. Viết ca IPN thành công đến muộn, ngày bắt đầu đã qua, đơn đã hủy, thanh toán bằng phương thức khác, tiền vượt tổng và callback lặp.
2. Với đơn còn chờ có gói: form đối soát cho chọn lại ngày, hiển thị kỳ dự kiến; backend kiểm tra toàn kỳ không chồng lấn trước khi cập nhật và chốt trong cùng transaction.
3. Với đơn đã hủy: không hồi sinh; ghi nhận hoàn ngoài ngân hàng qua ledger chung và đánh dấu đã xử lý. Nếu khách muốn mua tiếp thì đặt một đơn mới.
4. Giữ thông tin gốc của giao dịch/callback và ngày nhận tiền; ngày quản lý xử lý là trường riêng. Thông báo lý do cụ thể khi thiếu hàng/lớp đã bắt đầu/lịch không hợp lệ, không chỉ thông báo không thể chốt.
5. Nếu chốt thất bại, rollback toàn bộ quyền tập/đăng ký lớp/trừ kho phát sinh, giữ giao dịch ở hàng đối soát; không mất tiền khỏi báo cáo hoặc tạo dữ liệu dở dang.
6. Callback sau khi đã hoàn hoặc đã xử lý trả xác nhận an toàn, không tạo quyền/trừ kho/ghi hoàn thêm lần nữa.
7. Chạy `php artisan test --compact --filter='PaymentReviewWorkflowTest|VnPayPaymentFlowTest|ManualRefundWorkflowTest|ManagerReportsTest'`; commit khi đạt.

## 9. Task 6 — Giỏ hàng không bị lỗi khi gói ngừng bán

**Modify:** `laravel/app/Services/GymCommerceService.php`, `laravel/app/Http/Controllers/GymStoreController.php`, `laravel/resources/views/gym/cart.blade.php`, `laravel/tests/Feature/GymCommerceTest.php`.

1. Viết ca thêm gói rồi quản lý ngừng bán; mở giỏ vẫn thành công và xóa món được. Thêm ca sản phẩm/lớp không còn tồn tại hoặc không còn nhận đăng ký.
2. Tách đọc dòng giỏ để hiển thị khỏi kiểm tra món đủ điều kiện mua; trả thông tin dòng không hợp lệ có lý do, không `abort(404)` cả giỏ và không biến thành giá 0 hợp lệ.
3. Checkout kiểm tra lại toàn bộ món/giá/giảm giá/tồn/chỗ/ngày ngay trước ghi. Nếu dữ liệu thay đổi ảnh hưởng điều khách vừa xác nhận, yêu cầu xem lại; không bỏ món hoặc thay đơn âm thầm.
4. Xóa/cập nhật dòng giỏ luôn kiểm tra đúng chủ sở hữu; lỗi một món không làm mất các món khác.
5. Chạy `php artisan test --compact --filter='GymCommerceTest|CustomerAccountTest|ManagerGymPackagesTest'`; commit khi đạt.

## 10. Task 7 — Chặn lớp trùng giờ trong cùng giỏ trước khi nhận tiền

**Modify:** `laravel/app/Services/GymCommerceService.php`, `laravel/app/Services/GymTrainingService.php`, `laravel/tests/Feature/GymCommerceTest.php`, `laravel/tests/Feature/GymTrainingTest.php`.

1. Viết test hai lớp trong cùng giỏ có buổi trùng giờ: checkout bị từ chối và không tạo hóa đơn, giữ chỗ, khoản thu hay đăng ký lớp.
2. So sánh từng cặp khoảng lịch giữa các lớp mới trong giỏ, rồi với lịch đã đăng ký/đơn chờ của khách; hai lớp liền nhau không trùng được chấp nhận.
3. Tái kiểm tra trước chốt vì quản lý có thể đổi lịch sau khi đặt. Khi đã nhận tiền mà không thể cung cấp, hiển thị lý do và đưa tới quy trình quản lý/hoàn tiền thay vì một lỗi không có bước tiếp theo.
4. Chặn xếp thêm lịch làm trùng học viên đã đăng ký, hoặc yêu cầu xử lý các đăng ký bị ảnh hưởng trước khi lưu; không chỉ kiểm tra lịch nhân viên.
5. Chạy `php artisan test --compact --filter='GymCommerceTest|GymTrainingTest|PaymentReviewWorkflowTest'`; commit khi đạt.

## 11. Task 8 — Scheduler, hạn đơn và dữ liệu cần đối chiếu

**Modify:** `laravel/app/Services/GymPortalService.php`, `laravel/app/Console/Commands/ProcessGymPortal.php`, `laravel/routes/console.php`, `laravel/resources/views/gym/settings.blade.php`, `README.md`, `laravel/README.md`, `laravel/tests/Feature/GymPortalCompletionTest.php`.

1. Viết test đơn hết hạn chưa thu tiền → hủy và trả giữ hàng/chỗ; đơn có tiền hoặc đang đối soát không tự hủy; chạy lặp không nhân thông báo.
2. Xử lý quyền thử hết thời gian từ mốc thời gian thực; tác vụ nền chỉ cập nhật/nhắc, không là chốt bảo vệ duy nhất.
3. Hướng dẫn local `php artisan schedule:work`, môi trường triển khai gọi `php artisan schedule:run` mỗi phút. Ghi rõ người vận hành cấu hình tiến trình và kiểm tra `last_processed_at`; cảnh báo khi tác vụ chưa chạy hoặc lâu không chạy.
4. Dữ liệu đơn cũ không có hạn/thông tin khoản thu phải xuất hiện trong danh sách đối chiếu, không đoán ngày hoặc tự hủy hàng loạt.
5. Đối chiếu hóa đơn cũ thiếu chi tiết/tổng tiền; gắn người xử lý và bằng chứng. Thiếu ảnh sản phẩm/lịch lớp hiện tại là công việc bổ sung dữ liệu riêng, không đánh dấu đã sửa bằng test.
6. Chạy `php artisan test --compact --filter='GymPortalCompletionTest|PaymentReviewWorkflowTest|ManualRefundWorkflowTest|TrialAttendanceWorkflowTest'`; commit khi đạt.

## 12. Task 9 — Nghiệm thu đầy đủ, tài liệu và phát hành

### Bộ kiểm thử bắt buộc

| Nhóm | Tình huống phải chứng minh |
| --- | --- |
| Guest/auth | Tập thử yêu cầu đăng nhập, tạo tài khoản quay lại đúng form, không tự gửi, không redirect ra ngoài. |
| Customer | Chỉ xem/hủy yêu cầu và đơn của mình; không tự duyệt thử/thu/hoàn tiền; hồ sơ lấy đúng tài khoản. |
| Employee | Duyệt thử/check-in/thu tiền theo quyền được cấp; tắt quyền chặn cả URL và POST trực tiếp; không hoàn tiền như manager. |
| Manager | Duyệt/từ chối, cấu hình ngày thử, đối soát và hoàn tiền có chứng từ; xem được chuỗi lịch sử. |
| Tập thử | Chưa duyệt/chưa tới ngày/hết hạn/đã cấp trước đó/tài khoản khóa đều xử lý đúng; cấp lặp chỉ một quyền; không cộng doanh thu. |
| Mua gói sau thử | Dùng cùng tài khoản; không còn quyền thử bị coi thành kỳ gói tháng; tạo một gói trả phí, kỳ không chồng. |
| Thu và hoàn | Thu một phần → hoàn → hủy; hoàn vượt/lặp/đồng thời bị chặn; chứng từ gốc còn nguyên; số dư và Excel đúng. |
| VNPay | Sai chữ ký/sai tiền/lặp/đến muộn/đơn hủy/ngày bắt đầu qua/thiếu hàng; kết thúc được bằng chốt hợp lệ hoặc hoàn có ghi nhận. |
| Giỏ/lớp | Gói ngừng bán vẫn bỏ được; hai lớp trùng giờ bị chặn trước tạo hóa đơn; đổi lịch sau đặt có đường xử lý. |
| Hạn đơn | Hết hạn tự giải phóng giữ hàng khi không có request checkout mới; không hủy đơn đã nhận tiền/đang đối soát. |
| Đồng thời | Hai session cùng duyệt, thu, hoàn, chốt, hủy hoặc check-in: số tiền/quyền/tồn chỉ thay đổi một lần hợp lệ. |
| Hồi quy | Tìm/lọc/phân trang, quyền vai trò, RAG/Excel, báo cáo, banner, hồ sơ, khôi phục mật khẩu và hóa đơn ký vẫn đúng. |

### Thao tác và kiểm tra

1. Sau từng task chạy nhóm test hẹp tương ứng; PHP thay đổi phải chạy `vendor/bin/pint --dirty --format agent`.
2. Cuối cùng chạy `php artisan test --compact`, `node --test tests/js/carousel.test.js`, `npm run build`, `git diff --check` ở `laravel/`. Không báo đạt khi còn lỗi; ca bỏ qua phải nêu điều kiện riêng rõ ràng.
3. Dùng database QA riêng để chạy browser trong ChatGPT, không điều khiển ứng dụng native. Đăng nhập thật guest/customer/employee/manager bằng các phiên riêng, tạo tài khoản mới và đi hết các chuỗi trong bảng. Dùng dữ liệu QA nhận diện được và đối chiếu kết quả lưu.
4. Ca thu/hoàn tiền trong browser dùng ghi nhận QA trên database QA; không chuyển tiền ngân hàng thật. Giao dịch sandbox thật là ca riêng khi có khóa và IPN HTTPS; thiếu điều kiện phải ghi Chưa xác minh.
5. Bằng chứng: trước/sau số dư, trạng thái, một quyền được tạo, tồn/giữ hàng, lịch sử vào ra, kết quả Excel và ảnh các bước quan trọng. Ghi cả ca chặn quyền, không chỉ mở được trang.
6. Dọn đúng dữ liệu QA và khôi phục các cài đặt đã đổi; giữ báo cáo nghiệm thu, không đưa mật khẩu/token hay sao lưu DB vào Git.
7. Cập nhật `docs/nghiep-vu-goi-hoi-vien.md`, `laravel/docs/test-plan.md`, `README.md`, `laravel/README.md` và tài liệu chatbot bị ảnh hưởng. Phân biệt quy trình đã triển khai với ca đã được thử thực tế.
8. Rà migration trên bản sao DB, kiểm tra đối chiếu dữ liệu cũ, triển khai trên nhánh preview. Chỉ push/merge theo yêu cầu tiếp theo; kế hoạch hiện tại không thực hiện phát hành.

## 13. Điều kiện gọi là khép kín

- Hội viên chủ động tạo và dùng một tài khoản xuyên suốt thử/mua/tập; không tồn tại tài khoản mới do approval tạo mà khách không biết đăng nhập.
- Mỗi yêu cầu thử và mỗi đơn có trạng thái cuối rõ, người chịu trách nhiệm và hành động tiếp theo khi không đủ điều kiện.
- Mọi khoản tiền đã nhận được đối chiếu thành tiền đang giữ, đã chốt hoặc đã hoàn có chứng từ; không nhận tiền xong chỉ hiện một lỗi chung.
- Không cấp quyền, giữ/trừ kho, đăng ký lớp, hoàn tiền hoặc thu tiền hai lần vì gửi lặp/hai người thao tác.
- Thử miễn phí không biến thành gói trả phí, không ghi doanh thu; tài khoản khóa/hết quyền không vào tập được.
- Bộ test liên quan và toàn bộ hồi quy đạt; chuỗi browser đủ vai trò có bằng chứng; điều kiện chưa có như IPN thật ghi riêng, không gộp thành Đã test full.

## 14. Thứ tự và nhật ký quyết định

Thứ tự đề nghị: **Task 0 → 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9**. Mỗi task có kiểm thử trước/sau và commit riêng để review/khôi phục được; không gom tất cả vào một lần sửa lớn.

| Quyết định | Cơ sở | Phương án khác chưa chọn |
| --- | --- | --- |
| Tập thử gắn tài khoản | Người dùng đồng ý bổ sung login/register; tránh tạo tài khoản ngầm. | Giữ lead guest rồi tự tạo tài khoản khi staff duyệt. |
| Quyền thử riêng theo ngày | Gói trả phí hiện theo tháng; dùng chung `THOIHAN` gây hiểu sai. | Tạo một gói tháng giả và gọi là thử ngày. |
| Ledger hoàn tiền bổ sung | Giữ bằng chứng khoản thu và đối soát báo cáo theo thời điểm. | Xóa/sửa số tiền thu cũ để cho phép hủy. |
| Đơn đã hủy không hồi sinh | Hàng/chỗ đã giải phóng; giữ nguyên ý nghĩa lịch sử. | Chuyển khoản thu sang đơn thay thế tự động; cần cơ chế phân bổ tiền riêng ngoài phạm vi lần này. |
| Một nguồn tạo đơn mới | Checkout/hạn/QR/đối soát nhất quán, không tiếp tục nhân hai mô hình chờ. | Duy trì hai quy trình bán mới và đồng bộ vá ở UI. |
| Nghiệm thu tách tự động/browser/sandbox | Kết quả 22/10 còn lỗi và thiếu giao dịch thật; không suy từ mở trang thành toàn bộ nghiệp vụ đạt. | Gộp mọi loại kiểm tra thành một nhãn Test full. |

### Nhật ký triển khai và nghiệm thu — 27/10/2026

- `php artisan test --compact`: 236 tests, 235 passed, 1 skipped, 1.621 assertions. Test bị skip cần MySQL account-provisioning server riêng trên cổng 3318.
- `node --test tests/js/carousel.test.js` (4/4), `npm run build`, `vendor/bin/pint --dirty --format agent` và `git diff --check` đều đạt.
- Ba migration mới đã chạy trên MySQL local sau khi sao lưu CSDL và thư mục upload. Không reset dữ liệu ứng dụng.
- Browser ChatGPT: quản lý đăng nhập được, mở dashboard/khách/báo cáo/cài đặt/tài liệu chatbot; nhân viên đăng nhập được, mở sản phẩm/tập thử/hóa đơn/check-in; nhân viên vào `/quan-ly` nhận 403; số điện thoại không tồn tại bị từ chối check-in; khách chưa đăng nhập vào `/my/don-hang` bị chuyển về trang đăng nhập.
- Trang đăng ký hội viên và đường quay lại từ `?trial=1` đã mở được. Không tạo tài khoản mới trong phiên này vì thao tác nhập mật khẩu mới cần người dùng tự hoàn tất trên trình duyệt; đăng ký và xác thực quyền khách đã được kiểm thử bằng feature tests.
- RAG: Laravel vẫn hiển thị 18 tài liệu lưu trong MySQL, nhưng API tại `RAG_API_URL` không phản hồi; chưa kiểm thử câu trả lời live hoặc đồng bộ chỉ mục. Không có RAG server đang lắng nghe ở cổng 8090.
- Scheduler: lệnh `gym:process --mail` được đăng ký chạy mỗi phút; trạng thái quản lý báo scheduler chưa chạy. Cần người vận hành khởi chạy `php artisan schedule:work` trong terminal riêng hoặc cấu hình `schedule:run` mỗi phút trên host.
- Không tạo/thu/hoàn đơn mới trong CSDL đang dùng, không chuyển tiền, không gọi VNPay sandbox. Trang đối chiếu hiện có hóa đơn cũ thiếu/lệch chi tiết, sản phẩm thiếu ảnh và chưa có lớp học tương lai; cần dữ liệu xác thực từ chủ phòng tập, không tự điền giả.
