# The Gym — Laravel

Ứng dụng quản lý một phòng tập, viết bằng Laravel, Blade, Tailwind CSS và MySQL. Khách có thể mua gói/sản phẩm, đăng ký lớp và thanh toán tại quầy hoặc VNPay sandbox. Nhân viên xử lý hóa đơn, check-in và lịch lớp học; quản lý theo dõi hoạt động, báo cáo, nội dung website, tài khoản nhân viên và tài liệu chatbot. Website không còn chức năng PT; dữ liệu PT cũ được giữ để tra cứu lịch sử.

README ở thư mục gốc mô tả đầy đủ nghiệp vụ, tài khoản local, cấu hình VNPay và quy trình sao lưu. Xem thêm [kế hoạch kiểm thử, kết quả mới nhất và giới hạn kiểm tra](docs/test-plan.md).

## Yêu cầu

- PHP 8.3 trở lên với các extension Laravel cần dùng; bật `zip` để nhập/xuất Excel.
- Composer, Node.js 20.19+ hoặc 22.12+, npm.
- MySQL 8 trở lên.

## Cài đặt local trên Windows/Laragon

Tạo database MySQL rỗng trước, sau đó chạy trong thư mục `laravel/`:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Trong `.env`, đặt `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, cùng tên database và thông tin đăng nhập MySQL của máy. Không dùng thông tin đăng nhập mẫu cho máy khác. Tiếp theo:

```powershell
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
```

Mở `http://127.0.0.1:8000`. Tài khoản quản lý/nhân viên mẫu và cách cấu hình email được ghi trong [README gốc](../README.md). Chỉ dùng các tài khoản mẫu ở môi trường local.

Mở thêm một cửa sổ PowerShell riêng ở `laravel/` và chạy `php artisan schedule:work` để xử lý đơn quá hạn và nhắc lịch. Khi triển khai, cấu hình hệ thống gọi `php artisan schedule:run` mỗi phút. Trang Quản lý → Cài đặt hiển thị cảnh báo nếu Scheduler chưa chạy trong hơn 3 phút.

Chatbot gọi dịch vụ RAG riêng; cấu hình `RAG_API_URL` và `RAG_ADMIN_TOKEN` trong `.env` khi cần chạy dịch vụ đó. Hướng dẫn khởi chạy nằm trong [README gốc](../README.md).

VNPay là tùy chọn; cấu hình `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_RETURN_URL` và IPN công khai qua HTTPS trước khi bật giao dịch sandbox. Thiếu khóa thì trang thanh toán không hiển thị VNPay. IPN đã xác thực mới xác nhận thanh toán; test tự động giả lập callback có chữ ký và không thay cho giao dịch thật với sandbox.

## Kiểm thử

Test Laravel dùng database MySQL riêng `qly_phonggym_test`, được cấu hình trong `phpunit.xml`. Tạo database này trước khi chạy; không đổi test sang database local có dữ liệu.

```powershell
php artisan test --no-coverage
vendor/bin/pint --dirty --format agent
npm run build
```

Kết quả kiểm thử mới nhất và giới hạn nghiệm thu được ghi trong [docs/test-plan.md](docs/test-plan.md). Kiểm thử VNPay tự động dùng callback có chữ ký giả lập; thanh toán sandbox đầu-cuối cần khóa và endpoint HTTPS công khai.
