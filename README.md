# The Gym — hệ thống quản lý phòng tập

Ứng dụng web Laravel quản lý hoạt động của một phòng tập: khách mua gói và sản phẩm, đăng ký lớp học; nhân viên quản lý sản phẩm, hóa đơn, check-in và lịch lớp; quản lý theo dõi nhân sự cùng doanh thu. Website không còn chức năng PT; dữ liệu PT cũ được giữ để tra cứu lịch sử. Khách có thể thanh toán tại quầy hoặc qua VNPay sandbox khi máy chủ đã cấu hình khóa cổng. Trợ lý RAG hỗ trợ hỏi đáp nghiệp vụ trên website. Phạm vi và quy tắc nghiệp vụ được ghi tại [tài liệu nghiệp vụ](docs/nghiep-vu-goi-hoi-vien.md).

## Cấu trúc repository

| Thư mục | Nội dung |
| --- | --- |
| `laravel/` | Ứng dụng Laravel hiện tại, gồm mã nguồn, migration, seed, giao diện và kiểm thử. |
| `QL_GYM/` | Bản C# lưu trữ, không thuộc ứng dụng Laravel đang triển khai. |
| `docs/` | Nghiệp vụ hiện hành và tài liệu kế hoạch của project Laravel. |

## Nghiệp vụ hiện có

- **Khách vãng lai:** xem gói, sản phẩm và lớp học; đăng ký tài khoản trước khi gửi yêu cầu tập thử. Chỉ duyệt mới tạo quyền tập thử; gửi yêu cầu không tự cấp quyền.
- **Khách hàng:** đăng ký/đăng nhập, khôi phục mật khẩu qua email, cập nhật hồ sơ được phép, gửi yêu cầu thử tập, mua gói/sản phẩm/lớp qua giỏ hàng và theo dõi hóa đơn, quyền tập cùng lịch sử vào ra. Yêu cầu hủy đơn đang chờ sẽ khóa thao tác thanh toán cho đến khi nhân viên hủy hoặc xác nhận tiếp tục.
- **Nhân viên:** xử lý yêu cầu thử, hóa đơn mới và đơn gói cũ còn lịch sử; ghi nhận khoản thu tiền mặt/chuyển khoản, chốt đơn, giao hàng và check-in/check-out. Nhân viên có thể tạo/sửa sản phẩm, cập nhật tồn và ảnh; chỉ quản lý được xóa sản phẩm. Danh sách hóa đơn có tìm kiếm, lọc và phân trang.
- **Cửa hàng:** khách tìm và xem sản phẩm, quản lý giỏ hàng/địa chỉ, chọn nhận tại quầy hoặc giao hàng; giá và khuyến mãi lấy từ MySQL. Hệ thống giữ hàng trong đơn chờ, chỉ trừ tồn khi hóa đơn được xác nhận đã thu đủ; hủy đơn hợp lệ giải phóng phần giữ. Tồn kho không thể giảm thấp hơn số lượng đang được giữ cho đơn chờ.
- **Lớp học:** khách xem/đăng ký lớp; nhân viên/quản lý quản lý lịch và hồ sơ chuyên môn cần thiết cho lớp học.
- **Khách hàng:** xem đơn theo trạng thái và phân trang; xem riêng gói đang dùng, sắp bắt đầu và đã hết hạn; lịch sử chỉ hiển thị dữ liệu thuộc đúng tài khoản. Có thể thanh toán đơn gói và hóa đơn đủ điều kiện qua VNPay khi máy chủ đã cấu hình cổng.
- **Quản lý:** xem bảng điều khiển và báo cáo theo khoảng ngày, gồm tiền nhận, tiền hoàn và thu ròng; xuất Excel. Quản lý hội viên, gói, sản phẩm, tài khoản/quyền nhân viên, thông tin phòng tập, banner và tài liệu chatbot. Hoàn tiền thủ công được ghi bằng chứng vào sổ riêng sau khi đã trả tiền bên ngoài website; hệ thống không tự chuyển tiền hoàn.
- **Trợ lý The Gym (RAG):** nhận câu hỏi nghiệp vụ tối đa 500 ký tự và chuyển đến dịch vụ RAG qua API. Tài liệu gốc trong màn quản lý được lưu ở MySQL; dịch vụ RAG giữ bản lập chỉ mục để tra cứu. Quản lý có biểu mẫu tiếng Việt để thêm/sửa câu hỏi thường gặp, chỉnh hướng dẫn dài, tìm tài liệu, tạm ngừng/bật lại/xóa và nhập tối đa 100 câu hỏi từ Excel sau bước xem trước. Câu hỏi sản phẩm lấy dữ liệu hiện hành và có thể hiện ảnh cùng nút mở trang chi tiết. Câu hỏi có thể dùng để tính toán đơn giản nếu đủ dữ kiện; giá mẫu luôn được ghi rõ là tham khảo. Chatbot cũng trả lời kiến thức chung về gym và tập luyện ngoài tài liệu, gắn nhãn nội dung tham khảo; câu hỏi ngoài chủ đề bị từ chối. Nguồn tài liệu không hiển thị trong khung chat. Người dùng nhấn Enter để gửi câu hỏi, Shift+Enter để xuống dòng.
- **Banner trang chủ:** ảnh tự chuyển; các nút chọn ảnh vẫn dùng được và banner tạm ngưng khi người dùng đang thao tác hoặc bật chế độ giảm chuyển động.
- **Tài khoản nhân viên:** quản lý có thể khóa/mở tài khoản. Tài khoản bị khóa không đăng nhập lại được và phiên đang mở sẽ bị kết thúc ở yêu cầu tiếp theo. Người dùng đang đăng nhập có thể đổi mật khẩu sau khi xác nhận mật khẩu hiện tại.

## Quy tắc quan trọng

- Khoản thu tại quầy do nhân viên ghi nhận và xác nhận. Với VNPay, IPN đã xác thực chữ ký, mã giao dịch, số tiền và trạng thái mới là nguồn chốt thanh toán; trang khách quay về từ cổng không tự xác nhận đơn. Sự kiện thanh toán lặp không được tạo thêm gói hoặc trừ kho lần hai.
- Đơn mới dùng cùng giỏ hàng và hóa đơn. Các đơn gói cũ chỉ được giữ để tra cứu/xử lý lịch sử. Nếu khách đã yêu cầu hủy, nhân viên phải hủy đơn hoặc xác nhận tiếp tục trước khi thu/chốt.
- Khi khách yêu cầu tập thử, quản lý có thể đối chiếu dữ liệu khách cũ chỉ khi tài khoản và số điện thoại khớp. Không tự ghép người theo email/số điện thoại hoặc tạo tài khoản hộ khách.
- Mỗi đơn đã xác nhận chỉ tạo một gói hội viên. Một khách không thể có hai kỳ đã thanh toán chồng thời gian.
- Ngày hết hạn được lưu như mốc không còn quyền tập, ở đầu ngày theo múi giờ `Asia/Ho_Chi_Minh`. Khách tập đến hết ngày liền trước mốc này. Ví dụ gói một tháng bắt đầu 31/01/2026 hết quyền lúc 00:00 ngày 01/03/2026, nên ngày tập cuối là 28/02/2026.
- Tài khoản quản lý/nhân viên mẫu chỉ được tạo khi `APP_ENV=local`; không dùng thông tin mẫu ở môi trường thật.

Đây là đồ án giới hạn một cơ sở phòng tập. VNPay sandbox được tích hợp cho đơn gói và hóa đơn cửa hàng/lớp; thanh toán thật cần cấu hình `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, URL trả kết quả và IPN công khai qua HTTPS. Hoàn tiền tự động, nhiều chi nhánh và xác minh tài khoản nhiều bước chưa nằm trong phạm vi.

Để chạy tác vụ hết hạn đơn và nhắc lịch ở local, mở một cửa sổ PowerShell riêng trong `laravel/` và chạy `php artisan schedule:work`. Máy chủ triển khai cần gọi `php artisan schedule:run` mỗi phút. Trang Quản lý → Cài đặt cảnh báo nếu tác vụ chưa chạy trong hơn 3 phút.

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

### Thanh toán VNPay sandbox

Điền `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_RETURN_URL` trong `.env`; URL sandbox và thời hạn thanh toán có sẵn trong `.env.example`. Cấu hình IPN trỏ đến endpoint `/thanh-toan/vnpay/ipn` của website. Máy chủ VNPay phải gọi được endpoint này qua HTTPS. Khi thiếu khóa, giao diện ẩn lựa chọn VNPay và khách vẫn có thể thanh toán tại quầy. Không đưa khóa VNPay vào frontend hoặc Git. Hệ thống ghi nhận thành công từ IPN hợp lệ; việc đối soát hoặc hoàn tiền vẫn cần quản lý xử lý ngoài cổng.

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

Lần kiểm tra ngày 22/10/2026 đã đăng nhập và chạy các luồng chính bằng browser với vai trò quản lý, nhân viên và hội viên; kết quả và phần chưa kiểm chứng được ghi trong [kế hoạch kiểm thử](laravel/docs/test-plan.md). Bộ Laravel gần nhất có **198 bài đạt, 12 bài thất bại, 1 bài lỗi ngoại lệ và 1 bài bỏ qua**. Chưa xác nhận giao dịch VNPay sandbox thật hoặc check-in với một gói hội viên đang hiệu lực.

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
