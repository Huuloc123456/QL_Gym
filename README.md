# The Gym — hệ thống quản lý phòng tập

Ứng dụng web Laravel phục vụ quy trình hội viên của một phòng tập: khách xem và mua gói, nhân viên xác nhận khoản thu và ghi nhận lượt tập, quản lý theo dõi gói cùng doanh thu. Trợ lý RAG hỗ trợ hỏi đáp nghiệp vụ trên website. Phạm vi và quy tắc nghiệp vụ được ghi tại [tài liệu nghiệp vụ](docs/nghiep-vu-goi-hoi-vien.md).

## Cấu trúc repository

| Thư mục | Nội dung |
| --- | --- |
| `laravel/` | Ứng dụng Laravel hiện tại, gồm mã nguồn, migration, seed, giao diện và kiểm thử. |
| `QL_GYM/` | Dự án C# gốc dùng làm tài liệu tham khảo nghiệp vụ và giao diện. |
| `docs/` | Đặc tả nghiệp vụ và kế hoạch chuyển đổi. |

## Nghiệp vụ hiện có

- **Khách vãng lai:** xem gói đang mở bán, chi tiết giá/thời hạn và gửi yêu cầu tập thử. Yêu cầu tập thử không tạo tài khoản hoặc quyền vào tập.
- **Khách hàng:** đăng ký, đăng nhập, khôi phục mật khẩu qua email, cập nhật hồ sơ được phép, chọn gói và ngày bắt đầu, theo dõi đơn/gói/lịch sử vào ra, gửi yêu cầu hủy đơn đang chờ.
- **Nhân viên:** đơn gói hội viên có màn hình chờ/lịch sử/tất cả, tìm kiếm và phân trang; khoản thu được ghi nhận thủ công bằng tiền mặt/chuyển khoản. Hóa đơn mua hàng, PT và lớp học được xử lý ở luồng `/hoa-don`, hiện chưa gộp vào danh sách `/staff/don-hang`. Màn check-in tìm theo mã/tên/số điện thoại, lọc ngày/trạng thái và phân trang cả lượt đang tập lẫn lịch sử.
- **Cửa hàng:** khách tìm và xem sản phẩm, quản lý giỏ hàng/địa chỉ, chọn nhận tại quầy hoặc giao hàng; giá và khuyến mãi lấy từ MySQL. Hệ thống giữ hàng trong đơn chờ, chỉ trừ tồn khi nhân viên xác nhận đã thu đủ; hủy đơn hợp lệ giải phóng phần giữ.
- **Huấn luyện và lớp học:** khách xem/đăng ký lớp hoặc buổi PT, nhân viên/quản lý quản lý lịch và hồ sơ chuyên môn; hệ thống chặn lịch trùng, kiểm tra chỗ còn và tạo hóa đơn cùng luồng thanh toán chung.
- **Khách hàng:** xem đơn theo trạng thái và phân trang; xem riêng gói đang dùng, sắp bắt đầu và đã hết hạn; lịch sử vẫn thuộc đúng tài khoản. Luồng hóa đơn cửa hàng hiện có sai lệch đã biết ở số tiền đã ghi nhận trên lịch sử khách hàng; xem [kết quả kiểm thử](laravel/docs/test-plan.md).
- **Quản lý:** xem bảng điều khiển và báo cáo theo khoảng ngày. Báo cáo đơn gói hội viên tách đơn tạo trong kỳ, giá trị đơn được xác nhận theo `paid_at`, và khoản thu theo `received_at`; Excel gồm tổng quan, đơn và khoản thu. Luồng hóa đơn cửa hàng hiện chưa được tính đúng vào tổng tiền thực nhận và phương thức thu trong báo cáo; xem [kết quả kiểm thử](laravel/docs/test-plan.md). Quản lý cũng quản lý gói, tài khoản/quyền nhân viên, thông tin phòng tập và banner.
- **Trợ lý The Gym (RAG):** nhận câu hỏi nghiệp vụ tối đa 500 ký tự, chuyển đến dịch vụ RAG qua API và hiển thị nguồn. Quản lý có biểu mẫu tiếng Việt để thêm/sửa câu hỏi thường gặp, chỉnh hướng dẫn dài, tìm tài liệu, tạm ngừng/bật lại/xóa và nhập tối đa 100 câu hỏi từ Excel sau bước xem trước. Nhập hàng loạt chỉ dựng chỉ mục một lần; lỗi thì khôi phục tài liệu và chỉ mục cũ. Người quản lý thử câu trả lời bằng nút nổi chatbot trên website.
- **Tài khoản nhân viên:** quản lý có thể khóa/mở tài khoản. Tài khoản bị khóa không đăng nhập lại được và phiên đang mở sẽ bị kết thúc ở yêu cầu tiếp theo. Người dùng đang đăng nhập có thể đổi mật khẩu sau khi xác nhận mật khẩu hiện tại.

## Quy tắc quan trọng

- Chỉ nhân viên ghi nhận khoản tiền. Chọn chuyển khoản hoặc báo đã chuyển không tự xác nhận đơn; gói chỉ có hiệu lực sau khi thu đủ và nhân viên xác nhận.
- Mỗi đơn đã xác nhận chỉ tạo một gói hội viên. Một khách không thể có hai kỳ đã thanh toán chồng thời gian.
- Ngày hết hạn được lưu như mốc không còn quyền tập, ở đầu ngày theo múi giờ `Asia/Ho_Chi_Minh`. Khách tập đến hết ngày liền trước mốc này. Ví dụ gói một tháng bắt đầu 31/01/2026 hết quyền lúc 00:00 ngày 01/03/2026, nên ngày tập cuối là 28/02/2026.
- Tài khoản quản lý/nhân viên mẫu chỉ được tạo khi `APP_ENV=local`; không dùng thông tin mẫu ở môi trường thật.

Đây là đồ án giới hạn một cơ sở phòng tập; chưa hỗ trợ nhiều chi nhánh, cổng thanh toán trực tuyến, hoàn tiền tự động hay xác minh tài khoản nhiều bước. Khoản thanh toán đều do nhân viên ghi nhận thủ công bằng tiền mặt hoặc chuyển khoản.

## Công nghệ

- PHP 8.3+ (bật `ext-zip` trong Laragon để nhập/xuất `.xlsx`), Laravel 13, MySQL 8+, Node.js 20.19+ hoặc 22.12+
- Blade, Tailwind CSS 4 và Vite
- PhpSpreadsheet để đọc mẫu Excel và xuất báo cáo `.xlsx`
- PHPUnit; bộ kiểm thử dùng MySQL riêng `qly_phonggym_test` theo `laravel/phpunit.xml`
- Repository Pattern cho truy cập dữ liệu; Laravel policies cho quyền sở hữu đơn và thao tác nhân viên

## Chạy trên Windows/Laragon

Clone nhánh Laravel đang phát triển rồi mở PowerShell tại thư mục ứng dụng:

```powershell
git clone --branch preview/laravel-gym https://github.com/Huuloc123456/QL_Gym.git
Set-Location QL_Gym\laravel
```

Tạo file môi trường và khóa ứng dụng:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Tạo database MySQL rỗng (mặc định tên `qly_phonggym_laravel`), rồi cập nhật `DB_DATABASE`, `DB_USERNAME` và `DB_PASSWORD` trong `laravel/.env` theo cấu hình máy. Sau đó chạy:

```powershell
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
```

Mở `http://127.0.0.1:8000`. Nếu Laragon đã cung cấp PHP, Composer, MySQL và Node.js trong PATH, có thể dùng các thành phần đó; nếu không, mở terminal từ Laragon hoặc thêm đường dẫn cài đặt tương ứng vào PATH.

Nếu `php -m` chưa liệt kê `zip`, mở `php.ini` đang dùng bởi Laragon, bật dòng `extension=zip`, rồi khởi động lại PHP/Apache. Excel import/export cần tiện ích này.

Chatbot cần dịch vụ RAG chạy riêng. Mặc định Laravel gọi `http://127.0.0.1:8090/chat`; cập nhật `RAG_API_URL` và `RAG_API_TIMEOUT` trong `.env` nếu dịch vụ chạy ở địa chỉ khác.

Để dùng quản lý tài liệu chatbot, chạy project [SimpleRAGChatbot](https://github.com/phulee203/simple-rag-chatbot) riêng: tạo `.env` từ `.env.example`, cài `requirements.txt`, điền `GROQ_API_KEY`, tạo một token ngẫu nhiên dài và đặt cùng giá trị vào `RAG_ADMIN_TOKEN` của cả hai project. Trong Laravel, đặt thêm `RAG_ADMIN_TIMEOUT=180`. Chạy `python embed_pdfs.py` lần đầu để tạo chỉ mục, sau đó chạy `python rag_api.py` và để cửa sổ dịch vụ hoạt động. API mặc định chỉ lắng nghe trên máy local; không đổi host thành `0.0.0.0` khi không có mạng nội bộ được bảo vệ.

Khi chạy production, lưu token trong `.env` phía máy chủ và chạy `php artisan config:cache` sau khi cấu hình; không đưa token lên HTML, JavaScript hoặc Git. Ảnh banner được lưu trong `laravel/storage/app/public/hero-banners`; giữ thư mục này khi chuyển máy.

### Tài khoản mẫu local

| Vai trò | Tên đăng nhập | Mật khẩu |
| --- | --- | --- |
| Quản lý | `quanly` | `GymDemo@2026` |
| Nhân viên | `nhanvien` | `GymDemo@2026` |

Các tài khoản trên chỉ phục vụ môi trường local. Khách hàng có thể tự đăng ký trên trang web. Lệnh seed có thể chạy lại để bổ sung gói/tài khoản mẫu còn thiếu.

### Khôi phục mật khẩu

`.env.example` dùng `MAIL_MAILER=log`, nên môi trường local không gửi email ra ngoài. Link khôi phục được ghi trong `laravel/storage/logs/laravel.log`. Để nhận thư thật, cấu hình mailer/SMTP và địa chỉ gửi hợp lệ trong `.env`.

## Kiểm thử và build

Chạy từ thư mục `laravel/`. Trước khi chạy test, tạo database MySQL riêng tên `qly_phonggym_test` theo `phpunit.xml`; không trỏ cấu hình test vào database ứng dụng có dữ liệu.

```powershell
php artisan test --compact
npm run build
```

## Sao lưu và khôi phục

Sao lưu MySQL và các file người dùng cùng nhau; không lưu bản sao dưới `public/`. Ví dụ chạy trong PowerShell, thay tên database/user theo `.env`:

```powershell
$backup = "D:/Backups/TheGym/$(Get-Date -Format yyyyMMdd-HHmmss)"
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$ragProject = 'D:/Learning/Projects/SimpleRAGChatbot'
mysqldump --single-transaction --routines --triggers -u root -p qly_phonggym_laravel --result-file="$backup/database.sql"
Copy-Item ./laravel/storage/app/public "$backup/public-files" -Recurse
Copy-Item "$ragProject/knowledge_base" "$backup/knowledge-base" -Recurse
```

Khôi phục vào database đã tạo sẵn bằng `cmd /c "mysql -u root -p qly_phonggym_laravel < $backup/database.sql"`, sau đó chép `public-files` về `laravel/storage/app/public` và `knowledge-base` về thư mục project RAG. Không thay `.env` bằng bản mẫu; giữ khóa và thông tin mail của máy nhận riêng. Thử khôi phục trên database riêng trước khi thay dữ liệu đang chạy.

Test dùng database MySQL riêng và làm mới schema của database đó. Kế hoạch, kết quả kiểm thử và các điểm còn cần kiểm tra nằm trong [test plan](laravel/docs/test-plan.md). Thiết kế và phạm vi các phần tra cứu, báo cáo, lịch sử khách hàng và quản lý RAG nằm trong [kế hoạch chức năng](docs/plans/2026-10-21-tra-cuu-bao-cao-chatbot.md).

## Tài liệu

- [Nghiệp vụ khách hàng và gói hội viên](docs/nghiep-vu-goi-hoi-vien.md)
- [Kế hoạch kiểm thử](laravel/docs/test-plan.md)
- [Kế hoạch chuyển đổi Laravel](docs/plans/2026-10-02-laravel-migration.md)
