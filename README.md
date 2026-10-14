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
- **Nhân viên:** tìm và lọc đơn, ghi nhận nhiều khoản tiền mặt hoặc chuyển khoản, kiểm tra đã thu đủ rồi xác nhận; tra cứu hội viên, xem quyền tập, check-in/check-out và đóng lượt quên check-out kèm lý do; xử lý yêu cầu tập thử.
- **Quản lý:** xem bảng điều khiển, doanh thu từ đơn đã thanh toán, tạo/sửa/mở/ngừng bán gói, và tạo/sửa tài khoản nhân viên.
- **Trợ lý The Gym (RAG):** nhận câu hỏi nghiệp vụ tối đa 500 ký tự, chuyển đến dịch vụ RAG qua API và hiển thị câu trả lời cùng nguồn tài liệu. Nếu dịch vụ chưa chạy, website báo trợ lý tạm thời không sẵn sàng; các chức năng còn lại vẫn dùng được.

## Quy tắc quan trọng

- Chỉ nhân viên ghi nhận khoản tiền. Chọn chuyển khoản hoặc báo đã chuyển không tự xác nhận đơn; gói chỉ có hiệu lực sau khi thu đủ và nhân viên xác nhận.
- Mỗi đơn đã xác nhận chỉ tạo một gói hội viên. Một khách không thể có hai kỳ đã thanh toán chồng thời gian.
- Ngày hết hạn được lưu như mốc không còn quyền tập, ở đầu ngày theo múi giờ `Asia/Ho_Chi_Minh`. Khách tập đến hết ngày liền trước mốc này. Ví dụ gói một tháng bắt đầu 31/01/2026 hết quyền lúc 00:00 ngày 01/03/2026, nên ngày tập cuối là 28/02/2026.
- Tài khoản quản lý/nhân viên mẫu chỉ được tạo khi `APP_ENV=local`; không dùng thông tin mẫu ở môi trường thật.

Đây là đồ án giới hạn một phòng tập. Hệ thống không quản lý tồn kho/sản phẩm, chi nhánh, PT, lớp học, cổng thanh toán, hoàn tiền tự động hay xác minh tài khoản nhiều bước.

## Công nghệ

- PHP 8.3+, Laravel 13, MySQL 8+, Node.js 20.19+ hoặc 22.12+
- Blade, Tailwind CSS 4 và Vite
- PHPUnit; bộ kiểm thử dùng MySQL riêng `qly_phonggym_test` theo `laravel/phpunit.xml`
- Repository Pattern cho truy cập dữ liệu; Laravel policies cho quyền sở hữu đơn và thao tác nhân viên

## Chạy trên Windows/Laragon

Clone repo rồi mở PowerShell tại thư mục ứng dụng:

```powershell
git clone https://github.com/tman44121/QLY_PHONGGYM.git
Set-Location QLY_PHONGGYM\laravel
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
npm ci
npm run build
php artisan serve
```

Mở `http://127.0.0.1:8000`. Nếu Laragon đã cung cấp PHP, Composer, MySQL và Node.js trong PATH, có thể dùng các thành phần đó; nếu không, mở terminal từ Laragon hoặc thêm đường dẫn cài đặt tương ứng vào PATH.

Chatbot cần dịch vụ RAG chạy riêng. Mặc định Laravel gọi `http://127.0.0.1:8090/chat`; cập nhật `RAG_API_URL` và `RAG_API_TIMEOUT` trong `.env` nếu dịch vụ chạy ở địa chỉ khác.

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

Test dùng database MySQL riêng và làm mới schema của database đó. Kế hoạch, kết quả test thủ công và các điểm còn cần kiểm tra nằm trong [test plan](laravel/docs/test-plan.md).

## Tài liệu

- [Nghiệp vụ khách hàng và gói hội viên](docs/nghiep-vu-goi-hoi-vien.md)
- [Kế hoạch kiểm thử](laravel/docs/test-plan.md)
- [Kế hoạch chuyển đổi Laravel](docs/plans/2026-10-02-laravel-migration.md)
