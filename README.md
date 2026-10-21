# The Gym — hệ thống quản lý phòng tập

Ứng dụng Laravel quản lý một phòng gym: mua gói, cửa hàng 57 sản phẩm từ SQL, lớp học, PT, thu tiền, giao nhận, check-in và quản trị dữ liệu. Trợ lý RAG hỗ trợ hỏi đáp nghiệp vụ trên website. Phạm vi và quy tắc nghiệp vụ được ghi tại [tài liệu nghiệp vụ](docs/nghiep-vu-goi-hoi-vien.md).

## Cấu trúc repository

| Thư mục | Nội dung |
| --- | --- |
| `laravel/` | Ứng dụng Laravel hiện tại, gồm mã nguồn, migration, seed, giao diện và kiểm thử. |
| `QL_GYM/` | Dự án C# gốc dùng làm tài liệu tham khảo nghiệp vụ và giao diện. |
| `docs/` | Đặc tả nghiệp vụ và kế hoạch chuyển đổi. |

## Nghiệp vụ hiện có

- **Khách vãng lai:** xem gói đang mở bán, chi tiết giá/thời hạn và gửi yêu cầu tập thử. Nhân viên có thể duyệt quyền tập thử sau khi xác minh khách; mỗi số điện thoại được duyệt một lần, tối đa 7 ngày theo procedure gốc.
- **Khách hàng:** đăng ký, đăng nhập, khôi phục mật khẩu qua email, cập nhật hồ sơ được phép, chọn gói và ngày bắt đầu, theo dõi đơn/gói/lịch sử vào ra, gửi yêu cầu hủy đơn đang chờ.
- **Nhân viên:** tìm và lọc đơn, ghi nhận nhiều khoản tiền mặt hoặc chuyển khoản, kiểm tra đã thu đủ rồi xác nhận; tra cứu hội viên, xem quyền tập, check-in/check-out và đóng lượt quên check-out kèm lý do; xử lý yêu cầu tập thử.
- **Quản lý:** xem bảng điều khiển, doanh thu từ đơn đã thanh toán, tạo/sửa/mở/ngừng bán gói, và tạo/sửa tài khoản nhân viên.
- **Trợ lý The Gym (RAG):** nhận câu hỏi nghiệp vụ tối đa 500 ký tự, chuyển đến dịch vụ RAG qua API và hiển thị câu trả lời cùng nguồn tài liệu. Nếu dịch vụ chưa chạy, website báo trợ lý tạm thời không sẵn sàng; các chức năng còn lại vẫn dùng được.

## Quy tắc quan trọng

- Chỉ nhân viên ghi nhận khoản tiền. Chọn chuyển khoản hoặc báo đã chuyển không tự xác nhận đơn; gói chỉ có hiệu lực sau khi thu đủ và nhân viên xác nhận.
- Mỗi đơn đã xác nhận chỉ tạo một gói hội viên. Một khách không thể có hai kỳ đã thanh toán chồng thời gian.
- Ngày hết hạn được lưu như mốc không còn quyền tập, ở đầu ngày theo múi giờ `Asia/Ho_Chi_Minh`. Khách tập đến hết ngày liền trước mốc này. Ví dụ gói một tháng bắt đầu 31/01/2026 hết quyền lúc 00:00 ngày 01/03/2026, nên ngày tập cuối là 28/02/2026.
- Tài khoản quản lý/nhân viên mẫu chỉ được tạo khi `APP_ENV=local`; không dùng thông tin mẫu ở môi trường thật.

- **Cửa hàng:** tìm/lọc 57 sản phẩm, album ảnh, giá khuyến mãi, giỏ hàng lưu trong SQL, ưu đãi theo hạng, địa chỉ mặc định, nhận tại quầy hoặc giao hàng. Giữ hàng khi đặt; trigger gốc trừ tồn đúng một lần khi xác nhận.
- **Lớp/PT:** danh sách, chuyên môn, đăng ký trả phí, số buổi, sĩ số, lịch học không trùng giáo viên/học viên, hoàn thành buổi và chấm dứt đăng ký có lý do.
- **Quản lý SQL:** danh mục khách/nhân sự/chức vụ/hạng/chuyên môn/sản phẩm/lớp, tồn kho, hóa đơn, ký RSA-SHA256 và kiểm tra tệp bị sửa; xem 22 bảng, 11 procedure, function, trigger; lịch sử đăng nhập và tài khoản MySQL qua các procedure gốc.
- **Tài khoản:** mật khẩu được băm; có lựa chọn OTP email khi đăng nhập khách. Tên `admin` trong SQL mang chức vụ PT, không được tự nâng thành quản lý.

Phạm vi vẫn là một phòng tập. SQL không có nghiệp vụ chi nhánh, cổng thanh toán hay hoàn tiền tự động; không bổ sung các module này. Giao nhận và xác nhận chuyển khoản do nhân viên thực hiện.

## Công nghệ

- PHP 8.3+, Laravel 13, MySQL 8+, Node.js 20.19+ hoặc 22.12+
- Blade, Tailwind CSS 4 và Vite
- PHPUnit; bộ kiểm thử dùng MySQL riêng `qly_phonggym_test` theo `laravel/phpunit.xml`
- Repository Pattern cho truy cập dữ liệu; Laravel policies cho quyền sở hữu đơn và thao tác nhân viên

## Chạy trên Windows/Laragon

Clone repo rồi mở PowerShell tại thư mục ứng dụng:

```powershell
git clone https://github.com/Huuloc123456/QL_Gym.git
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
php artisan gym:link-sql --roles
npm ci
npm run build
php artisan serve
```

Mở [trang chủ](http://127.0.0.1:8000/) hoặc [cửa hàng](http://127.0.0.1:8000/san-pham). Nếu Laragon đã cung cấp PHP, Composer, MySQL và Node.js trong PATH, có thể dùng các thành phần đó; nếu không, mở terminal từ Laragon hoặc thêm đường dẫn cài đặt tương ứng vào PATH.

Chatbot cần dịch vụ RAG chạy riêng. Mặc định Laravel gọi `http://127.0.0.1:8090/chat`; cập nhật `RAG_API_URL` và `RAG_API_TIMEOUT` trong `.env` nếu dịch vụ chạy ở địa chỉ khác.

### Tài khoản mẫu local

| Vai trò | Tên đăng nhập | Mật khẩu |
| --- | --- | --- |
| Quản lý | `quanly` | `GymDemo@2026` |
| Nhân viên | `nhanvien` | `GymDemo@2026` |

Các tài khoản trên chỉ phục vụ môi trường local. Khách hàng có thể tự đăng ký trên trang web. Lệnh seed có thể chạy lại để bổ sung gói/tài khoản mẫu còn thiếu.

### Khôi phục mật khẩu

`.env.example` dùng `MAIL_MAILER=log`, nên môi trường local không gửi email ra ngoài. Link khôi phục và mã OTP được ghi trong `laravel/storage/logs/laravel.log`. Theo lựa chọn hiện tại, ưu tiên thông báo trong web và tắt email nghiệp vụ. Để nhận thư thật về sau, cấu hình mailer/SMTP và địa chỉ gửi hợp lệ trong `.env`, rồi bật email nghiệp vụ ở trang cấu hình; OTP email chỉ hiện khi dùng mailer gửi thật.

## SQL và dữ liệu đang chạy

- Hiện web local dùng MySQL cổng **3307**, database **qly_phonggym_laravel_sql**. Database cũ `qly_phonggym_laravel` vẫn giữ nguyên; bản dump trước chuyển đổi nằm tại `laravel/storage/app/private/backups/before-sql-20261021.sql`.
- Migration nhập bản SQL được lưu tại `laravel/database/sql/ql_gym_mysql.sql`, giữ nguyên tên/định nghĩa 22 bảng, 11 procedure, 1 function và 1 trigger. Trình nhập bỏ qua lệnh xóa/tạo/chọn database trong file nguồn. Không chạy trực tiếp file SQL gốc lên database có dữ liệu.
- Với database mới: tạo schema rỗng, sửa cấu hình `.env`, chạy các lệnh migrate/seed/link phía trên. `gym:link-sql` liên kết tài khoản/gói/dữ liệu Laravel cũ và có thể chạy lại. Hai role MySQL chỉ có quyền trong database ứng dụng; không tạo sẵn tài khoản MySQL mới.
- Ở môi trường khác local, điền `GYM_ADMIN_DB_USERNAME`, `GYM_ADMIN_DB_PASSWORD` bằng tài khoản quản trị riêng nếu dùng màn hình quản lý tài khoản MySQL. Chỉ quản lý tên có tiền tố `gym_web_`; tài khoản web và tài khoản MySQL là hai loại khác nhau.
- Trên Laragon, cấu hình OpenSSL được tìm cạnh PHP. Nếu cài đặt khác, đặt `GYM_OPENSSL_CONFIG` tới `openssl.cnf`. Khóa ký riêng lưu ở storage private; chứng từ HTML có thể in PDF bằng trình duyệt, không gửi khóa riêng cho người tải.
- Các lớp có sẵn đều là lịch năm 2025; quản lý cần tạo lớp/lịch hiện hành để nhận đăng ký mới. Không đổi ngày lịch sử tự động.
- SQL có hóa đơn #182 và #138 thiếu chi tiết và một số hóa đơn khác lệch tổng dòng hàng: web cho xem và liệt kê trong Cấu hình phòng gym, nhưng chặn thu/chốt/ký cho tới khi đối chiếu. Báo cáo giữ nguyên số liệu nguồn và hiện cảnh báo khi trong kỳ có chứng từ chưa khớp. Ảnh mã sản phẩm 58–62 trỏ nhầm loại hàng trong nguồn; giữ dữ liệu, dùng ảnh chờ cập nhật, quản lý có thể tải ảnh đúng.
- Phí giao hàng mặc định bằng 0; quản lý có thể đặt phí cố định trong Cấu hình phòng gym. Phí không hưởng giảm giá hạng khách và được chốt theo từng đơn. Giá PT lấy từ hợp đồng SQL đã có hoặc giá quản lý thiết lập; cần gán chuyên môn trước khi mở bán PT.

## Các chức năng hoàn thiện bổ sung

- **Đơn quá hạn:** đơn mới mặc định có 24 giờ thanh toán, quản lý đặt được 1–168 giờ. Tự hủy và giải phóng hàng/chỗ khi hết hạn nếu chưa nhận tiền; không tự hủy đơn đã thu một phần. Chặn khoản thu đầu tiên khi đã quá hạn ngay cả trước lượt chạy Scheduler. Hạn trên đơn cũ không thay đổi khi sửa cấu hình.
- **Thông báo trong web:** tạo khi đặt đơn, nhận tiền, chốt/hủy, đổi trạng thái nhận/giao, xếp/hủy lịch; nhắc trước khi hết gói và trước buổi PT/lớp. Mỗi tài khoản chỉ xem/đánh dấu thông báo của mình. Thông báo nhắc được chống trùng khi chạy lại.
- **Email nghiệp vụ:** mặc định tắt theo yêu cầu, cấu trúc gửi SMTP đã sẵn sàng. Nếu bật nhưng driver log/array thì ghi rõ là thư kiểm tra, không coi là đã gửi thật. Gửi lỗi thử tối đa 3 lần, không lộ lỗi kết nối/mật khẩu vào giao diện. Xử lý thư ngoài transaction đặt hàng.
- **Báo cáo:** quản lý lọc tối đa 366 ngày, xem doanh thu hóa đơn theo ngày lập, thực thu theo ngày ghi nhận, nhóm sản phẩm/gói/lớp/PT trước giảm giá, hàng bán chạy, lượt tập, tồn thấp và gói sắp hết hạn. Xuất CSV UTF-8 có bảo vệ công thức từ tên khách. Không suy diễn khoản thu lịch sử khi SQL chỉ có hóa đơn mà thiếu biên nhận.
- **Bảo mật tài khoản:** đổi mật khẩu bằng mật khẩu hiện tại, đồng bộ mật khẩu băm sang SQL và kết thúc các session khác.
- **Đặt hàng:** ẩn/tắt trường giao hàng khi nhận tại quầy, cập nhật phí/tổng tiền, hiển thị thông tin quầy và tài khoản ngân hàng nếu quản lý đã nhập đầy đủ.
- **Lịch:** xem tuần/tháng theo quyền khách/HLV/nhân viên/quản lý; các thao tác xếp/hủy vẫn ở cùng trang bên dưới lịch.
- **Liên hệ, hướng dẫn, chính sách:** các trang công khai, liên kết ở chân trang. Thông tin liên hệ/ngân hàng chưa được chủ phòng gym cung cấp nên để trống, không dùng dữ liệu giả. Quản lý điền ở `/quan-ly/cau-hinh`.
- **Đối chiếu dữ liệu:** trang cấu hình liệt kê hóa đơn thiếu/lệch chi tiết hoặc tổng tiền, ảnh cần cập nhật, PT thiếu giá/chuyên môn và liên kết tạo lớp hiện hành. Giữ nguyên lịch cũ và file nguồn.
- **Tiếng Việt:** giao diện, phân trang và các thông báo xác thực/kiểm tra dữ liệu thông dụng dùng locale `vi`.

Các phần mới chỉ thêm `gym_settings`, `gym_messages`, `gym_fulfillments.expires_at/paid_at`; không sửa bảng, procedure, function hoặc trigger gốc.

### Duy trì tác vụ tự động

Ngoài terminal chạy `php artisan serve`, mở terminal thứ hai tại thư mục `laravel` và giữ chạy:

```powershell
php artisan schedule:work
```

Scheduler chạy `gym:process --mail` mỗi phút, có khóa tránh chạy chồng. Email chỉ xử lý khi được bật trong cấu hình. Để xử lý thông báo trong web một lần, không gửi thư:

```powershell
php artisan gym:process
```

Trên máy hiện tại đã chạy server và Scheduler ẩn. Chúng cần khởi động lại khi khởi động lại máy. Log tiến trình nằm trong `laravel/storage/logs/local-server.*.log` và `local-scheduler.*.log`. Lần chạy gần nhất hiển thị ở Cấu hình phòng gym. Bản sao lưu trước phần bổ sung nằm ở `laravel/storage/app/private/backups/before-portal-20261021.sql`.

## Kiểm thử và build

Chạy từ thư mục `laravel/`. Trước khi chạy test, tạo database MySQL riêng tên `qly_phonggym_test` theo `phpunit.xml`; không trỏ cấu hình test vào database ứng dụng có dữ liệu.

```powershell
php vendor/phpunit/phpunit/phpunit
node --test tests/js/*.test.js
npm run build
```

Test dùng database MySQL riêng và làm mới schema của database đó. Kế hoạch, kết quả test thủ công và các điểm còn cần kiểm tra nằm trong [test plan](laravel/docs/test-plan.md).

## Tài liệu

- [Nghiệp vụ khách hàng và gói hội viên](docs/nghiep-vu-goi-hoi-vien.md)
- [Kế hoạch kiểm thử](laravel/docs/test-plan.md)
- [Ma trận chức năng SQL và tiến độ triển khai](docs/plans/2026-10-21-hoan-thien-laravel-theo-sql.md)
- [Kế hoạch chuyển đổi Laravel](docs/plans/2026-10-02-laravel-migration.md)
