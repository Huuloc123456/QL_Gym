# Kịch bản kiểm thử đầy đủ — The Gym

Ngày chuẩn bị: **28/10/2026**. Website local: **http://127.0.0.1:8000**.

Tài liệu hướng dẫn thao tác thủ công; kết quả tự động ghi riêng tại `laravel/docs/test-plan.md`. Một ca có trong tài liệu chưa có nghĩa đã được người dùng chạy đạt.

## 1. Chuẩn bị và thứ tự chạy

1. Mở cửa sổ thường cho khách, cửa sổ ẩn danh hoặc trình duyệt khác cho nhân viên. Các tab cùng trình duyệt thường dùng chung phiên đăng nhập.
2. Chạy mục 2–4 trước để xem dữ liệu gốc của bộ demo. Chạy luồng mua hàng mục 5 **một lần** bằng `demo_khach`; ghi mã hóa đơn vừa tạo.
3. Chạy mục 6–10 sau. Các ca sửa giá, khóa tài khoản, hủy đơn và đối soát làm thay đổi dữ liệu; ghi trạng thái trước/sau và phục hồi cấu hình khi xong.
4. Chỉ dùng tên bắt đầu `DEMO`/`QA` và email `example.test`. Không sửa khách, hóa đơn hay 57 sản phẩm nguồn để làm bài test.
5. Trước mục 5, quản lý mở Báo cáo, chọn riêng ngày đang test, ghi doanh thu và tiền thực nhận làm mốc. Dữ liệu SQL có hóa đơn lịch sử nên không kỳ vọng tổng toàn hệ thống bằng tổng của riêng ca test.

MySQL local hiện dùng cổng **3307**, database **qly_phonggym_laravel_sql**. Dữ liệu đã được sao lưu trước sửa tại `laravel/storage/app/private/backups/before-sync-fixes-20261028-160745.sql`, và có snapshot sau tạo demo tại `laravel/storage/app/private/backups/demo-ready-20261028.sql`. Không chạy `migrate:fresh`, `db:wipe` hoặc PHPUnit trên database này. Các bản sao chứa dữ liệu tài khoản, chỉ giữ riêng trên máy.

Để khởi động thủ công, mở hai terminal tại `laravel/`:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan schedule:work
```

Máy Laragon này cần extension ZIP khi xuất Excel. Nếu PHP báo thiếu ZIP, cấu hình riêng của phiên chạy hiện tại là:

```powershell
$env:PHP_INI_SCAN_DIR = 'C:\laragon\www\DOAN\laravel\storage\app\private\runtime\php-conf.d'
```

Seeder đã chạy trên database local. Lệnh sau chỉ cần khi chuẩn bị trên máy khác sau khi migrate/import SQL:

```powershell
php artisan db:seed --class=GymDemoScenarioSeeder --no-interaction
```

Seeder chỉ chạy ở `local/testing`, không tạo trùng hóa đơn, phiếu thu hoặc tài khoản khi gọi lại. **Không reset** dữ liệu, mật khẩu hay ngày của bộ demo đã có. Danh sách mã thực tế nằm trong `laravel/storage/app/private/demo/scenario.json`; mã dưới đây áp dụng máy hiện tại. Nếu dùng ngày khác, lịch tương lai và quyền thử có thể đã hết hạn; xem ngày thực trên giao diện, không đổi đồng hồ máy để test.

## 2. Tài khoản và quyền

Seeder hiện tạo 11 tài khoản demo, mật khẩu ban đầu **`DemoGym@2026`**. Cơ sở dữ liệu được seed từ phiên bản cũ có thể giữ tài khoản thứ 12 `demo_pt`; đây là tài khoản nhân viên thường trong hệ thống hiện hành, không còn quyền hay chức năng PT. Seeder không đổi mật khẩu/dữ liệu tài khoản đã tồn tại.

| Tên đăng nhập | SĐT / mã khách | Mục đích và kết quả đăng nhập |
|---|---|---|
| `demo_quanly` | 0999000101 | Quản lý: tất cả mục vận hành, danh mục, nhân viên, báo cáo, cài đặt, CSDL, đối soát |
| `demo_nhanvien` | 0999000102 | Lễ tân: sản phẩm, đơn/hóa đơn, yêu cầu thử, check-in; không vào quản lý |
| `demo_kho` | 0999000103 | Nhân viên chỉ quản lý sản phẩm; không thu tiền, xem hóa đơn hoặc check-in |
| `demo_pt` (chỉ DB cũ) | 0999000104 | Tài khoản nhân viên cũ; không có màn PT/lớp hoặc lịch dạy trong bản hiện tại |
| `demo_khach` | 0999000105 / 185 | Khách A chưa mua; dành riêng cho luồng mua mới mục 5 |
| `demo_khach_b` | 0999000106 / 186 | Khách B: kiểm tra bảo mật chéo, hóa đơn hủy và đối soát |
| `demo_hoivien` | 0999000107 / 187 | Gói hiệu lực và đơn sản phẩm; có thể còn chứng từ PT/lớp lịch sử trong DB cũ |
| `demo_hethan` | 0999000108 / 188 | Gói hết hạn; xem lịch sử được, vào tập bị từ chối |
| `demo_tuonglai` | 0999000109 / 189 | Gói đã trả tiền, bắt đầu 04/11/2026; trước ngày này chưa được vào tập |
| `demo_khoa` | 0999000110 / 190 | Tài khoản ngừng hoạt động: đăng nhập bị từ chối |
| `demo_tapthu` | 0999000111 / 191 | Đã duyệt thử 3 ngày từ 28/10; còn hạn thì được check-in |
| `demo_dangtap` | 0999000112 / 192 | Có lượt check-in đang mở; dùng test chặn vào hai lần/check-out |

Không tạo mới role PT. Tài khoản `demo_pt` nếu còn từ lần seed cũ chỉ là nhân viên thường và không có chức năng giảng dạy. Ba quyền nhân viên được quản lý bật/tắt riêng là **đơn hàng, tập thử, check-in**; quản lý sản phẩm là quyền độc lập.

## 3. Dữ liệu mẫu để tìm nhanh

| Đối tượng | Mã / trạng thái ban đầu | Dùng để test |
|---|---|---|
| DEMO Bình nước | Sản phẩm #63; giá gốc 100.000, khuyến mãi 80.000, tồn thực 30 | Mua mới; chưa có đơn mẫu giữ mặt hàng này |
| DEMO Khăn tập | Sản phẩm #64; 100.000 | Các hóa đơn lịch sử, giao nhận, thu một phần |
| DEMO Dây kháng lực giữ chỗ | Sản phẩm #65; tồn thực 1, đang giữ 1, còn bán 0 | Chặn bán vượt kho/xóa hàng đang giữ |
| DEMO Gói 1 tháng | Gói #5; 300.000 | Mua gói và kiểm tra ngày hết quyền |
| DEMO Gói 3 tháng | 800.000; trường thời lượng 3 | Gói trả phí 3 tháng; procedure duyệt thử dùng 3 **ngày** |
| Phí giao hàng | 30.000đ | Không giảm theo hạng khách; nhận tại quầy phí 0 |
| Hạng khách demo | Giảm 10% | Tính trên tiền hàng/dịch vụ sau khuyến mãi, trước phí giao |

### Hóa đơn đã tạo sẵn

| Mã | Chủ sở hữu | Trạng thái / số tiền | Hành động nên thử |
|---|---|---|---|
| #185 | demo_hoivien | Gói đang hiệu lực, đã thanh toán 270.000 | Xem gói, check-in, tải/ký chứng từ |
| #186 | demo_hethan | Gói đã thanh toán, lùi ngày để hết hạn | Xem mục hết hạn, thử check-in bị chặn |
| #187 | demo_tuonglai | Gói đã thanh toán, bắt đầu tương lai | Xem ngày bắt đầu, chưa được vào tập |
| #188 | demo_dangtap | Gói đã thanh toán | Check-out lượt đang mở |
| #189 | demo_hoivien | Nhận tại quầy, 90.000, chưa thu | Thu thiếu/thu đủ/chốt, rồi sẵn sàng nhận → đã nhận |
| #190 | demo_hoivien | Giao địa chỉ, 120.000, đã thu 50.000 | Còn phải thu 70.000; thu đủ rồi mới chốt |
| #191 | demo_hoivien | Giao địa chỉ, đã thu/chốt 210.000, chờ chuẩn bị | Đang giao → đã giao, hai bên cùng thấy |
| #192 | demo_hoivien | Nhận tại quầy, đã thanh toán và nhận 90.000 | Lịch sử; chặn thao tác quay ngược trạng thái |
| #193 | demo_khach_b | Đã hủy | Không thu/chốt lại |
| #194 | demo_hoivien | Chưa thanh toán, giữ duy nhất 1 dây | Xóa sản phẩm bị chặn; hủy hóa đơn để trả chỗ |
| #195–#196 (nếu còn trong DB cũ) | demo_hoivien | Chứng từ lớp/PT lịch sử | Chỉ xem lịch sử; không tạo lịch, thu/chốt thêm hoặc kích hoạt dịch vụ đã ngừng |
| #197 | demo_khach_b | Chưa chốt, giao dịch `DEMO-REVIEW-01` cần đối soát | Nhân viên thấy cảnh báo; quản lý xử lý mục 9 |

Đơn chưa thanh toán có hạn giữ hàng. Khi hết hạn, scheduler có thể hủy đơn demo chưa thu; không coi khác biệt do thời gian trôi qua là lỗi. Đơn đã thu một phần hoặc đang đối soát không tự bị hủy theo hạn này.

## 4. Khách vãng lai, tài khoản và chatbot

| Mã | Các bước | Kết quả mong đợi |
|---|---|---|
| G01 | Chưa đăng nhập, mở Trang chủ, Gói tập, Sản phẩm, Liên hệ, Hướng dẫn, Chính sách; mở `/lop-hoc`, `/huan-luyen-vien`, `/lich-tap` | Trang còn hỗ trợ hiển thị; các đường dẫn lớp/PT/lịch đã ngừng trả 404 |
| G02 | Ở trang chủ, đợi 5 giây; thử chấm ảnh, tạm dừng, tiếp tục; di chuột/đặt focus vào banner | Tự chuyển, không có hai nút Trước/Sau; tạm dừng khi tương tác; không nháy mất ảnh |
| G03 | Thu cửa sổ 390px, mở/đóng menu, nhấn Escape; dùng Tab và phóng to 200% | Liên kết dùng được, nội dung không bị sidebar che, bảng cuộn trong khung |
| G04 | Tìm DEMO Bình nước; thử nhóm sản phẩm, khoảng giá, có hàng và phân trang; mở chi tiết | Giá 80.000, đúng thông tin; #65 không xuất hiện khi lọc còn hàng |
| G05 | Chưa đăng nhập, mở `/gio-hang`, `/my/goi-tap`; thử mua | Chuyển đăng nhập; không có hóa đơn tự phát |
| G06 | Đăng ký `qa_khach_01`, email `qa_khach_01@example.test`, SĐT chưa có, mật khẩu ≥8 ký tự; thử trùng email/SĐT, sai xác nhận | Bản hợp lệ tạo khách và đăng nhập; bản sai có lỗi, không nhân đôi KHACHHANG |
| G07 | Đăng nhập sai; sau đó đúng; đăng xuất rồi dùng nút Back và tải lại trang riêng tư | Sai bị từ chối; đúng chuyển về trang theo vai trò; sau đăng xuất trang riêng tư yêu cầu đăng nhập |
| G08 | Demo khách mở Thông tin cá nhân, đổi tên/ngày sinh/giới tính; nhập SĐT đã dùng ở B | Giá trị hợp lệ lưu và đồng bộ SQL; SĐT trùng bị từ chối; email/tên đăng nhập không tự đổi |
| G09 | Bảo mật tài khoản: sai mật khẩu hiện tại, xác nhận không khớp; sau đó đổi hợp lệ, đăng xuất/đăng nhập lại | Bản sai không thay đổi mật khẩu; mật khẩu mới dùng được. Ghi mật khẩu đã đổi; seeder không khôi phục |
| G10 | Quên mật khẩu: nhập email đã có rồi email không có | Cùng thông báo chung để không lộ tài khoản. Với cấu hình hiện tại thư ghi log, không tới hộp thư thật |
| G11 | Kiểm tra tùy chọn OTP email | Với MAIL_MAILER=log hiện tại, tùy chọn OTP bị ẩn. Chỉ test gửi/nhập OTP thật sau khi cấu hình gửi thư; không đánh dấu đạt gửi email khi chưa có SMTP |
| G12 | Đăng nhập `demo_khoa` | Bị từ chối, không xem được trang riêng tư |

### Chatbox — chạy ít hơn 10 câu/phút

Mở **Hỏi The Gym**, kiểm tra từng câu dưới đây. Khi muốn dùng lại ba nút gợi ý, tải lại trang. Chat có giới hạn 500 ký tự/câu; vượt tần suất trả 429 là hành vi dự kiến.

| Mã | Thao tác / câu hỏi | Kết quả mong đợi |
|---|---|---|
| C01 | Mở, đóng bằng nút X/Escape, mở lại; dùng Tab | Panel đúng vị trí, tiêu điểm quay về nút mở; không che hết màn hình nhỏ |
| C02 | Để trống/nhập khoảng trắng; Shift+Enter; Enter khi có nội dung | Trống không gửi; Shift+Enter xuống dòng; Enter gửi đúng một lần; đang gửi không gửi chồng |
| C03 | Bấm “Thời hạn gói” | Giải thích số tháng, ví dụ bắt đầu 31/01/2027 có ngày tập cuối 28/02/2027 |
| C04 | Bấm “Thanh toán” | Hướng dẫn chờ đối chiếu/IPN hợp lệ; không nói tự kích hoạt ngay sau bấm chuyển khoản |
| C05 | Bấm “Quên mật khẩu” | Có đường dẫn `/quen-mat-khau`, không đòi cung cấp mật khẩu trong chat |
| C06 | “Sản phẩm DEMO Bình nước giá bao nhiêu?” | 80.000đ; lượng có thể đặt theo DB (ban đầu 30), kèm nguồn; sau mục 5 số lượng giảm đúng |
| C07 | “Phí giao hàng bao nhiêu?”; “Có gói tập nào?”; “Bạn có sản phẩm gì?”; “Tôi muốn đăng ký lớp yoga”; “Bài tập yoga nào phù hợp người mới?” | Dữ liệu phí/gói/sản phẩm lấy từ web; báo rõ không còn đặt lớp/PT; vẫn trả lời kiến thức tập luyện chung an toàn |
| C08 | Quản lý sửa giá trên một **sản phẩm QA riêng**, khách tải lại và hỏi tên QA đó | Giá/khả dụng mới xuất hiện ngay từ DB; hóa đơn đã chụp giá cũ vẫn giữ nguyên |
| C09 | “Đơn hàng của tôi?”, “Danh sách khách hàng”, câu chứa OTP/token | Chỉ hướng dẫn xem tài khoản, không trả đơn, SĐT hay dữ liệu riêng của bất kỳ ai |
| C10 | Hỏi một câu ngoài phạm vi, như “Hướng dẫn từ tài liệu nâng cao chưa công bố?” | Nếu RAG riêng chưa chạy: hiện thông báo trợ lý bận; form dùng lại được, không lộ lỗi/khóa máy chủ |
| C11 | Màn hình 390×844 và desktop; câu dài/nhiều dòng/có dấu; mạng tạm mất | Tin nhắn cuộn trong khung, tiếng Việt đúng, không tràn ngang; khi lỗi có thông báo và gửi lại được |

Sau khi chat chạm giới hạn riêng, đăng nhập vẫn phải hoạt động với giới hạn riêng của đăng nhập. Không dùng việc hỏi chat liên tục để khóa nhầm form đăng nhập. Khi đổi nhiều tài khoản test liên tiếp cùng IP, đăng nhập vẫn có giới hạn5lần/phút; đợi theo thông báo429 rồi tiếp tục.

Chat tra cứu công khai từ DB hoạt động độc lập. API tài liệu local `laravel/rag_api.py` hiện đã chạy ở cổng8090, hỗ trợ tài liệu dài và nhập Excel qua quản lý. Khi chưa có khóa Groq, chat trả trích đoạn có nguồn và ghi rõ chưa dùng AI. Bổ sung khóa vào `GROQ_API_KEY` trong `.env` mới bật nhánh Groq viết câu trả lời; chưa có kết quả thử khóa thật trên máy này. Nếu dừng API, câu cần tài liệu sẽ báo trợ lý bận. Không đánh dấu “AI/Groq đầu-cuối đạt” chỉ vì DB và tìm kiếm tài liệu trả lời được.

## 5. Luồng mua mới để chứng minh đồng bộ hai bên

Đăng nhập **demo_khach** ở cửa sổ khách và **demo_nhanvien** ở cửa sổ nội bộ. Khách đã có hạng giảm 10%, địa chỉ demo. **Không** thêm nhiều loại vào cùng giỏ trong năm ca đầu; mỗi ca tạo một hóa đơn để dễ đối chiếu. Chọn tiền mặt/tại quầy khi đặt vì máy chưa có khóa VNPay.

| Mã | Khách thao tác | Nhân viên thao tác | Đối chiếu hai bên |
|---|---|---|---|
| M01 | Sản phẩm #63 → thêm 2 → Giỏ → Giao theo địa chỉ, chọn địa chỉ demo → Đặt; ghi mã H1 | Mở H1; thử chốt trước khi thu; thu 100.000, tải lại; thu tiếp 74.000; chốt đủ; Đang giao → Đã giao | Tiền hàng 160.000, giảm 16.000, phí 30.000, **174.000**. Trước chốt tồn thực 30, đang giữ 2, còn bán 28. Thu một phần chưa kích hoạt/chốt. Chốt xong tồn thực 28, hết giữ 2. Khách thấy lịch sử hai lần thu và trạng thái giao |
| M02 | Thêm 1 bình #63 → Nhận tại quầy → Đặt, ghi H2 | Thu **72.000** → chốt → Sẵn sàng nhận → Đã nhận | 80.000 − 8.000, phí 0; tồn thực sau cả H1/H2 là **27**; khách thấy đã nhận |
| M03 | Gói DEMO 1 tháng → giỏ/đặt, chọn bắt đầu hôm nay, ghi H3 | Thu **270.000**, chốt ngày bắt đầu đúng với khách | Gói chỉ có quyền sau chốt; mục Hóa đơn và Đơn gói có cùng H3, không đếm hai đơn; Gói hội viên hiện đúng tháng đã chụp; check-in được |
| M04 | Khách mở H1–H3, tải lại Thông báo và Đơn hàng | Quản lý mở Báo cáo đúng ngày, xuất Excel và CSV | So với mốc trước M01: thêm **3 hóa đơn**, tổng thu **516.000đ**, giảm giá **54.000đ**, phí giao 30.000đ; đối chiếu bằng đúng mã H1–H3 |

Ngày D là ngày thực chạy bài test. Không đổi ngày hệ thống để làm cho gói hoặc tập thử mẫu còn hiệu lực; nếu mẫu đã hết hạn, dùng một fixture QA được tạo trong cơ sở dữ liệu test cô lập.

### Giỏ, giá, tồn và hủy — ca lỗi cần thử

| Mã | Các bước | Kết quả mong đợi |
|---|---|---|
| M07 | Tăng/giảm số lượng trong giỏ; nhập 0, số âm, lớn hơn tồn; thử đặt giỏ trống | 0 xóa dòng; số sai bị chặn; không có tổng âm/đơn rỗng |
| M08 | Khách cho một gói **QA riêng** vào giỏ; quản lý ngừng mở bán; khách mở lại giỏ | Trang vẫn mở; báo không còn mở bán, nút đặt bị chặn; xóa dòng được, không 404 toàn giỏ |
| M09 | Hai khách cùng đặt sản phẩm QA chỉ còn một cái | Đơn đầu giữ hàng; đơn sau bị từ chối; hủy/xử lý hết hạn đơn đầu thì hàng trở lại |
| M10 | Mở #65 hoặc hỏi chatbot; quản lý thử xóa #65 đang được #194 giữ | Khả dụng 0, lọc “còn hàng” không có; xóa bị chặn, dữ liệu còn nguyên. Sau khi thu/chốt #194 vẫn xử lý được |
| M11 | Nhân viên sửa tồn #65 xuống 0 khi vẫn còn giữ 1; nhập giá khuyến mãi vượt giá gốc | Báo lỗi, không phá khả năng hoàn tất đơn đã đặt |
| M12 | Đặt một đơn QA, quản lý đổi giá gói/sản phẩm rồi khách xem đơn cũ | Đơn cũ giữ giá/thời lượng snapshot; đơn mới dùng giá mới |
| M13 | Khách tạo đơn chưa thu, bấm gửi yêu cầu hủy; nhân viên duyệt hủy | Khách chưa tự xóa đơn; nhân viên thấy yêu cầu, hủy xong trả tồn/chỗ; không thu/chốt đơn đã hủy |
| M14 | Với #190 đã thu một phần, thử hủy hoặc chốt ngay | Hủy không được khi đã nhận tiền; chốt thiếu tiền bị chặn; thu thêm đúng **70.000** mới chốt được |
| M15 | Bấm lại xác nhận/refresh sau khi chốt; thử thu quá số còn thiếu | Không trừ kho/kích hoạt gói/ghi thu hai lần; số quá mức bị chặn |
| M16 | Giao địa chỉ nhưng chưa có địa chỉ; thêm/sửa/xóa/đặt mặc định tại `/my/dia-chi`; quay lại giỏ | Giao hàng bắt buộc địa chỉ hợp lệ của chính khách; nhận tại quầy không bắt nhập địa chỉ; đơn cũ giữ bản chụp địa chỉ |
| M17 | B đăng nhập, gõ `/hoa-don/185`; thử mã địa chỉ/đơn/lịch của A | 404/403; không thấy tên, tiền, tệp ký hay địa chỉ của A |
| M18 | Cấu hình hạn thanh toán 1 giờ, tạo đơn QA chưa thu và đợi quá hạn với scheduler chạy | Tự hủy, trả giữ hàng/chỗ. Tối thiểu **1 giờ**, không phải 1 phút. Test tự động đã giả lập thời gian; không cần sửa đồng hồ Windows |

## 6. Lễ tân và từng trạng thái hội viên

| Mã | Tài khoản / bước | Kết quả mong đợi |
|---|---|---|
| NV01 | `demo_nhanvien` mở Quản lý sản phẩm; tìm DEMO, mở sửa; truy cập `/quan-ly` | Sản phẩm thao tác được; trang quản lý bị 403; menu có hóa đơn, lịch, tập thử và check-in |
| NV02 | Mở #189, #190, #191, #192 và lọc trạng thái/nhận tại quầy/giao hàng | Đúng tiền, lịch sử thu, chủ sở hữu và phương thức; #190 còn 70.000; #191 có thể chuyển đang giao → đã giao |
| NV03 | Đơn chưa thanh toán → bấm giao hàng/đã nhận; đơn đã giao → đổi ngược | Bị chặn chuyển trạng thái sai; không giao hàng khi chưa chốt đủ tiền |
| NV04 | `/staff/check-in`, nhập **0999000107** hoặc mã khách187; check-in lần nữa rồi check-out | Lần đầu mở lượt; lần hai bị chặn; checkout ghi giờ ra, khách xem `/my/lich-su-tap` cùng lượt |
| NV05 | Nhập **0999000112** (`demo_dangtap`), sau đó checkout lượt đã mở | Check-in lặp bị chặn; check-out xong có thể vào lại nếu gói còn hạn |
| NV06 | Nhập **0999000108**, **0999000109**, **0999000110**, SĐT không có | Lần lượt chặn hết hạn, chưa tới ngày, tài khoản khóa và không tồn tại; không tạo CHECKIN sai |
| NV07 | Nhập **0999000111** khi quyền thử còn hạn | Được vào như quyền thử; sau khi quyền hết hạn thì bị chặn; không tạo gói trả phí giả |
| NV08 | Khách mới gửi form Trải nghiệm; nhân viên lọc “mới”, đánh dấu đã liên hệ; duyệt gói thử có thời lượng3 và xác minh danh tính | Yêu cầu đồng bộ; duyệt cấp quyền thử3ngày. Bỏ xác minh bị chặn; gửi lại cùng yêu cầu không cấp trùng; yêu cầu khác cùng SĐT đã thử bị chặn; không thu tiền tự động |
| NV09 | Duyệt yêu cầu mẫu SĐT **0999000901**, ghi tài khoản/khách kết quả trên màn hình | Không cấp đè mật khẩu khách đã có; nhân viên phải xác minh danh tính. Không cho người lạ chiếm tài khoản chỉ nhờ biết SĐT |
| NV10 | Gói hội viên của `demo_hoivien`, `demo_hethan`, `demo_tuonglai`, `demo_tapthu` | Hiện đúng nhóm đang tập/hết hạn/tương lai/tập thử; #186 vẫn xem được trong lịch sử hết hạn |
| NV11 | Lượt bị quên checkout: dùng chức năng đóng thủ công, nhập lý do | Chỉ người có quyền được làm; lưu nhân viên/lý do; khách thấy giờ ra đã đóng |

## 7. Kho và sản phẩm

Đăng nhập **demo_kho**. Dùng sản phẩm mới tên **QA Bình kiểm thử**, nhóm DEMO, giá100.000, khuyến mãi90.000, tồn5; không sửa sản phẩm #63 trong lúc đang đối chiếu mục5.

| Mã | Các bước | Kết quả mong đợi |
|---|---|---|
| K01 | Thêm QA Bình kiểm thử; khách tìm trên trang Sản phẩm | Hiện ngay, giá90.000, tồn5; đơn/giỏ dùng cùng dữ liệu SQL |
| K02 | Sửa tên, thương hiệu, mô tả, giá, tồn6; thử thiếu tên/âm tiền/âm tồn | Bản đúng đồng bộ; bản sai có lỗi, giữ giá trị cũ |
| K03 | Tải ảnh JPG/PNG hợp lệ, chọn ảnh chính; thử tệp khác ảnh/quá giới hạn form | Ảnh đúng hiện ở danh sách và chi tiết; tệp không hợp lệ bị từ chối, không để thực thi tệp |
| K04 | Tìm/lọc/phân trang sản phẩm nội bộ; tạo đơn QA giữ2sản phẩm | Tồn thực6, đang giữ2, còn bán4; khách thấy4; sau chốt tồnthực4, không trừ hai lần |
| K05 | Gõ `/hoa-don`, `/staff/check-in`, `/staff/yeu-cau-tap-thu`, `/quan-ly` | 403; không thể thu tiền hoặc cấp quyền tập nhờ đổi URL |
| K06 | Kho thử nhập URL xóa danh mục; quản lý xóa mặt hàng đang có giao dịch | Kho không được xóa; quản lý bị chặn xóa dữ liệu được tham chiếu/giữ. Mặt hàng không có giao dịch chỉ xóa bằng chức năng cho phép trên UI |

## 8. PT/lớp đã ngừng và dữ liệu lịch sử

| Mã | Các bước | Kết quả mong đợi |
|---|---|---|
| L01 | Guest/member/employee/manager mở `/lop-hoc`, `/lich-tap`, `/huan-luyen-vien` hoặc menu cũ | Trả 404; không lộ trang đăng ký, lịch hay hồ sơ PT |
| L02 | Hội viên gửi payload `kind=class` hoặc `kind=pt` tới giỏ/đặt hàng | Validation/backend từ chối; không sinh hóa đơn mới hoặc giữ chỗ |
| L03 | Mở giỏ cũ còn dòng lớp/PT lịch sử | Có nhãn dịch vụ đã ngừng và nút bỏ; không được checkout cho tới khi bỏ dòng cũ |
| L04 | Mở hóa đơn lớp/PT chưa thanh toán cũ | Giữ thông tin chứng từ; QR/thanh toán VNPay/thu quầy/chốt bị chặn và cần đối chiếu |
| L05 | Tra cứu hóa đơn lớp/PT cũ đã thanh toán | Vẫn xem được trạng thái, số tiền và lịch sử đã lưu; không tạo thêm lịch/PT hay thay đổi khoản thu |

## 9. Đối soát, chứng từ và VNPay

| Mã | Các bước | Kết quả mong đợi |
|---|---|---|
| TT01 | Lễ tân mở #197 | Thấy `DEMO-REVIEW-01`, cảnh báo đang đối soát; thu thêm/chốt/hủy bị chặn; không bị ẩn vì khác userID nhân viên |
| TT02 | Khách B mở #197; thử thanh toán tiếp và hủy | Không phát sinh thu thêm khi review chưa xử lý; trạng thái còn giữ để quản lý đối soát; scheduler không tự làm mất đơn |
| TT03 | `demo_quanly` → Đối soát; chọn ghi nhận thành công, kiểm tra số tiền/mặt hàng/chủ sở hữu, ghi chú≥8ký tự, tích đã kiểm tra, gửi | Giao dịch thành công; #197 được chốt khi đã đủ tiền; kho giảm đúng1. Tải lại/gửi lại không ghi/trừ lần hai |
| TT04 | Nếu muốn thử nhánh hoàn tiền, chọn nhánh này **thay cho TT03** trên dữ liệu review chưa xử lý; bỏ mã tham chiếu rồi gửi, sau đó nhập `DEMO-REFUND-01` và ghi chú rõ giả lập | Thiếu mã bị từ chối; hợp lệ ghi trạng thái đã hoàn/đối soát. Đây chỉ ghi **hoàn tiền đã làm bên ngoài**, không gọi API hoàn ngân hàng. Sau đó nhân viên mới xử lý hủy đơn theo quy trình |
| TT05 | Lễ tân/khách gõ `/quan-ly/doi-soat` hoặc gửi thao tác xử lý | 403; chỉ quản lý được quyết định. TT03 và TT04 là hai nhánh loại trừ; seeder chạy lại không reset review đã giải quyết |
| TT06 | Lễ tân mở một hóa đơn DEMO đã thanh toán, bấm ký/tạo chứng từ; khách tải HTML và bấm kiểm tra chữ ký | Có tệp và chữ ký RSA-SHA256; kiểm tra bản gốc hợp lệ, khách xem/tải được chứng từ của mình |
| TT07 | Sao chép tệp đã tải, sửa một chữ, tải bản sửa vào Kiểm tra chữ ký | Báo không hợp lệ; bản gốc trong hệ thống còn nguyên; khách B không tải được tệp của A |
| TT08 | Thử ký/thu/chốt hóa đơn SQL nguồn có cảnh báo thiếu chi tiết, như #138/#182 | Bị chặn và yêu cầu đối chiếu; không bịa dòng hàng hoặc tiền để hợp thức hóa lịch sử |
| TT09 | Không có khóa VNPay: mở checkout/hóa đơn | Không giả báo thành công; hướng dẫn thanh toán quầy. Nếu bật sandbox sau này phải có TMN/secret, callback/IPN HTTPS và test riêng |
| TT10 | Trên sandbox đã cấu hình: thành công, hủy, sai chữ ký/số tiền, IPN lặp, về trang Return trước IPN | Chỉ IPN xác thực mới chốt; Return không tự chốt; lặp không kích hoạt lại; bất thường vào review. Bộ PHPUnit giả lập đã kiểm các nhánh, chưa thay giao dịch thật |

**DEMO-REVIEW-01 là dữ liệu giả lập hoàn toàn**, không phải tiền đã chuyển từ ngân hàng. Hai nút quản lý luôn cần xác minh bên ngoài với giao dịch thật.

## 10. Quản lý: danh mục, phân quyền, báo cáo, nội dung và CSDL

| Mã | Các bước | Kết quả mong đợi |
|---|---|---|
| Q01 | `demo_quanly` kiểm tra sidebar trên desktop/mobile | Có sản phẩm, hóa đơn, lịch, tất cả danh mục, gói, tài khoản nhân viên, cài đặt, banner, chatbot, báo cáo, CSDL và đối soát |
| Q02 | Tạo gói QA2tháng600.000 → khách xem; sửa giá/thời lượng; tắt/bật mở bán | Thay đổi hiện ngay cho đơn mới, không sửa giá/thời lượng của đơn cũ; gói tắt biến khỏi trang khách |
| Q03 | Tạo/sửa nhóm sản phẩm, chức vụ và hạng khách QA; thử tên thiếu/giảm giá ngoài0–100 | Bản đúng lưu, bản sai không lưu; quan hệ SQL được giữ; xóa bản đang được dùng bị chặn |
| Q04 | Khách hàng → sửa hạng khách **QA riêng** từ0% sang10%; khách đặt một mặt hàng100.000 | Đơn mới giảm10.000, đơn đã đặt giữ nguyên; tên/SĐT và trạng thái tài khoản đồng bộ SQL/users |
| Q06 | Tài khoản nhân viên → tạo `qa_staff_01`, mật khẩu≥8, thông tin duy nhất; sửa thông tin, để trống mật khẩu | Tài khoản role nhân viên; sửa trống không reset mật khẩu. Không tạo quản lý bằng cách nhập trường lạ |
| Q07 | Phân quyền `qa_staff_01`: bật/tắt từng quyền đơn, thử, check-in; đăng nhập lại và nhập URL trực tiếp | Bật có chức năng, tắt bị403; sản phẩm vẫn có theo quy định hiện tại; không chỉ ẩn menu mà backend cũng chặn |
| Q08 | Khóa nhân viên QA đang có phiên đăng nhập; mở tiếp trang riêng tư; quản lý mở khóa | Phiên bị chặn khi tài khoản ngừng hoạt động, mở khóa đăng nhập lại được; không tự khóa quản lý đang sử dụng |
| Q09 | Báo cáo: lọc đúng ngày M01–M06, khoảng đảo ngược, >366ngày, không có dữ liệu | Số tiền/đơn SQL và đơn cũ đúng, không trùng cache; khoảng không hợp lệ bị báo lỗi; khoảng rỗng hiển thị0 |
| Q10 | Xuất Excel; mở CSV tại `/quan-ly/bao-cao/csv?from_date=2026-10-28&to_date=2026-10-28` | Excel có Tổng quan/Đơn hàng/Khoản thu; H1–H5 có đúng1dòng mỗi hóa đơn; giá trị, đã nhận, còn thu đúng; dữ liệu bắt đầu `=`, `+`, `-`, `@` không chạy công thức |
| Q11 | Cài đặt → đổi phí giao QA, giờ mở cửa/liên hệ/FAQ; khách tải lại trang và hỏi nguyên câu FAQ trong chat; phục hồi phí30.000 | Trang khách và chat phản ánh mới; hóa đơn cũ giữ phí đã chụp; không tự bịa thông tin ngân hàng. Giữ gửi email tắt khi chưa có SMTP |
| Q12 | Banner → tải ảnh hợp lệ, tiêu đề/alt, thứ tự, bật/tắt; thử ảnh sai định dạng; khách tải lại trang chủ | Chỉ ảnh bật hiển thị đúng thứ tự, tự chuyển; ảnh lỗi không làm mất toàn banner; validation bảo vệ upload |
| Q13 | Tài liệu chatbot → tạo/sửa FAQ/tài liệu, bật/tắt/xóa; nhập Excel mẫu, xem trước rồi xác nhận | Phân quyền/validation đúng; khi RAG chưa chạy, báo lỗi kết nối rõ và không giả báo đồng bộ thành công. Test đồng bộ/chất lượng câu trả lời tài liệu khi có backend riêng |
| Q14 | CSDL → xem danh sách bảng/procedure/function/trigger và mở từng bảng; gõ tên bảng ngoài danh sách cho phép | Vẫn đủ **22bảng nguồn,11procedure,1function,1trigger**; bảng phụ Laravel có thể nhiều hơn. Chỉ xem bảng cho phép; không SQL injection, không trả mật khẩu/hash/khóa |
| Q15 | Nhật ký → lọc username demo_nhanvien; khách xem Lịch sử đăng nhập của mình | Có thao tác/login tương ứng; không lộ lịch sử người khác cho khách; bản ghi thu/chốt/đối soát có dấu vết |
| Q16 | Quản trị tài khoản CSDL → thử tên không có tiền tố `gym_web_`, role không cho phép | Bị từ chối. Tài khoản MySQL khác tài khoản web; không thử tênroot/tài khoản đang kết nối |
| Q17 | Trên CSDL test chuyên dụng có quyền quản trị: tạo `gym_web_qa_reader`, cấp `gym_web_reader`, khóa/mở khóa; kiểm tra quyền bằng kết nối riêng | Procedure SQL gốc thực thi; tài khoản chỉ có quyền đã cấp. Ca này cần môi trường MySQL test đủ quyền, được ghi riêng là điều kiện phụ thuộc, không tạo tài khoản DB thật chỉ để bấm thử |
| Q18 | Khách/lễ tân thử vào các URL quản lý danh mục, nhân viên, cài đặt, CSDL | 403; thay mã trong URL không vượt quyền |

## 11. Thông báo và kiểm tra kết thúc

1. Sau thu/chốt hoặc xử lý đối soát, hội viên tải lại **Thông báo**. Nội dung phải gắn đúng người/đơn; bộ đếm giảm sau “Đánh dấu đã đọc”, “Đọc tất cả”. Không xem/đọc được thông báo của tài khoản khác bằng đổi mã.
2. Cuối test, checkout các lượt đang mở; ghi các hóa đơn đã thu một phần chưa chốt; xử lý yêu cầu hủy QA. Đừng xóa hóa đơn có tiền để làm sạch màn hình.
3. Khôi phục cấu hình phí giao30.000, quyền demo_nhanvien đủ3mục, demo_kho không có quyền thu/check-in, và các gói/ảnh QA theo trạng thái đã ghi. Không dùng seeder làm lệnh hoàn tác.
4. Kiểm tra trên hai cửa sổ: cùng mã hóa đơn, cùng trạng thái thu/giao nhận và quyền vào tập. Tải lại trang để lấy trạng thái mới; web không có cam kết đẩy dữ liệu thời gian thực qua WebSocket.

## 12. Phiếu ghi kết quả

Mỗi ca ghi một dòng; không đánh dấu đạt khi chưa thao tác:

| Mã ca | Tài khoản | Mã hóa đơn/khách/lịch | Mong đợi | Thực tế | Đạt/Lỗi/Chưa chạy/Phụ thuộc | Ảnh/ghi chú |
|---|---|---|---|---|---|---|
| M01 | demo_khach + demo_nhanvien | H1=… | 174.000, thu2lần, tồn28, đã giao | … | … | … |
| C06 | khách | #63 | Giá80.000, khả dụng theo DB | … | … | … |
| NV06 | demo_nhanvien | KH188/189/190 | Chặn vào tập | … | … | … |

Nếu lỗi, ghi URL, thời điểm, tài khoản, mã bản ghi, thao tác ngay trước lỗi và ảnh màn hình. Không chụp `.env`, mật khẩu, mã OTP hoặc khóa ký. Email SMTP thật, VNPay sandbox thật và sinh câu trả lời qua Groq chưa được nghiệm thu bằng dịch vụ thật trên máy này; API tra cứu tài liệu local đã hoạt động.
