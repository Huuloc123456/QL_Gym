# The Gym — Laravel

Ứng dụng quản lý một phòng tập, viết bằng Laravel, Blade, Tailwind CSS và MySQL. Khách có thể mua gói/sản phẩm, đăng ký PT/lớp và thanh toán tại quầy hoặc VNPay sandbox. Nhân viên quản lý sản phẩm và tồn kho, xử lý hóa đơn, check-in; quản lý theo dõi hoạt động, báo cáo, nội dung website, tài khoản nhân viên và tài liệu chatbot.

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

Chatbot tra cứu thông tin công khai từ DB (giá/tồn sản phẩm, gói, lớp/PT, phí giao, FAQ cấu hình). Hướng dẫn dài được tra cứu qua API local `rag_api.py`: chạy `python rag_api.py` bằng Python3.12+ ở terminal riêng. API dùng SQLite FTS5 và `RAG_ADMIN_TOKEN` trong cùng `.env` để bảo vệ quản trị tài liệu, không cần thêm thư viện. Khi chưa có `GROQ_API_KEY`, câu trả lời trích tài liệu có nguồn và ghi rõ chưa dùng mô hình AI. Có khóa thì nhánh Groq viết câu trả lời từ tài liệu truy xuất; không có embedding. Hướng dẫn cấu hình và dữ liệu chỉ mục nằm trong [README gốc](../README.md).

Xem [kịch bản test chi tiết theo từng tài khoản](../docs/kich-ban-test-day-du.md), gồm 12 tài khoản local, dữ liệu DEMO, luồng khách–nhân viên–quản lý và kết quả mong đợi. Seeder `GymDemoScenarioSeeder` chỉ bổ sung ở local/testing, không reset mật khẩu hay hóa đơn đã có.

VNPay là tùy chọn; cấu hình `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_RETURN_URL` và IPN công khai qua HTTPS trước khi bật giao dịch sandbox. Thiếu khóa thì trang thanh toán không hiển thị VNPay. IPN đã xác thực mới xác nhận thanh toán; test tự động giả lập callback có chữ ký và không thay cho giao dịch thật với sandbox.

## Kiểm thử

Test Laravel dùng database MySQL riêng `qly_phonggym_test`, được cấu hình trong `phpunit.xml`. Tạo database này trước khi chạy; không đổi test sang database local có dữ liệu.

```powershell
php artisan test --no-coverage
vendor/bin/pint --dirty --format agent
npm run build
```

Kết quả test tự động và kiểm thử thao tác trên trình duyệt được ghi trong [docs/test-plan.md](docs/test-plan.md). Báo cáo hiện cộng các khoản thu hóa đơn tại quầy và VNPay; lịch sử hóa đơn của khách hiển thị số đã thu. Kiểm thử VNPay hiện dùng callback giả lập; việc thanh toán sandbox đầu-cuối cần cấu hình khóa và endpoint HTTPS công khai.
