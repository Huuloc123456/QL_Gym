# The Gym — Laravel

Ứng dụng quản lý một phòng tập, viết bằng Laravel, Blade, Tailwind CSS và MySQL. Khách có thể xem/mua gói và dịch vụ; nhân viên tiếp nhận khoản thu, xử lý hóa đơn và check-in; quản lý theo dõi hoạt động, nội dung website, tài khoản nhân viên và tài liệu chatbot.

README ở thư mục gốc mô tả đầy đủ nghiệp vụ, tài khoản local và quy trình sao lưu. Xem thêm [kế hoạch kiểm thử và các lỗi đã phát hiện](docs/test-plan.md).

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

Chatbot gọi dịch vụ RAG riêng; cấu hình `RAG_API_URL` và `RAG_ADMIN_TOKEN` trong `.env` khi cần chạy dịch vụ đó. Hướng dẫn khởi chạy nằm trong [README gốc](../README.md).

## Kiểm thử

Test Laravel dùng database MySQL riêng `qly_phonggym_test`, được cấu hình trong `phpunit.xml`. Tạo database này trước khi chạy; không đổi test sang database local có dữ liệu.

```powershell
php artisan test --no-coverage
vendor/bin/pint --dirty --format agent
npm run build
```

Kết quả test tự động và kiểm thử thao tác trên trình duyệt được ghi trong [docs/test-plan.md](docs/test-plan.md). Phần kiểm thử trực tiếp mới nhất phát hiện một số sai lệch ở luồng hóa đơn cửa hàng; hiện tài liệu ghi rõ để xử lý ở lượt sửa tiếp theo.
