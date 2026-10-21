# Kế hoạch hoàn thiện Laravel theo ql_gym_mysql.sql

Ngày lập: 21/10/2026. Trạng thái: **đã tích hợp và kiểm tra trên MySQL `qly_phonggym_laravel` (Laragon MySQL 8.4.3, cổng 3306). Đã sao lưu trước khi áp dụng ba migration còn chờ; không reset database.**

## 1. Phạm vi đã thống nhất

- Thống nhất kiểu chữ toàn website, đặc biệt các dấu tiếng Việt.
- Banner trang chủ tự chuyển; bỏ hai nút Trước/Sau.
- Bổ sung bán vật phẩm gym, nhận tại quầy và giao hàng theo địa chỉ.
- Đáp ứng nghiệp vụ của **22 bảng, 11 stored procedure, 1 function và 1 trigger** trong `C:\laragon\www\DOAN\ql_gym_mysql.sql`.
- Theo lựa chọn của người dùng: giữ nguyên các bảng và stored procedure trong SQL. Không đổi tên, xóa hoặc thay thế chúng bằng các bảng nghiệp vụ Laravel khác. Giữ nguyên file SQL gốc; các phần bổ sung đặt riêng.
- Tiếp tục dùng Laravel, Blade, Tailwind và cấu trúc controller/service/repository/policy đang có. Theo yêu cầu bổ sung, đọc `QL_GYM` làm mẫu giao diện; giữ nguyên mã nguồn dự án gốc.

Phạm vi mới bao gồm cửa hàng, lớp học và PT, mở rộng so với giới hạn một phòng tập chỉ bán gói trước đây. SQL không có bảng chi nhánh, nhà cung cấp, cổng thanh toán hoặc hãng vận chuyển; kế hoạch dùng một phòng tập, xử lý giao hàng thủ công và xác nhận tiền tại quầy/chuyển khoản như hiện tại.

## 2. Những gì đang có và phần phải chuyển đổi

Laravel hiện có tài khoản khách/nhân viên/quản lý, ba quyền nghiệp vụ nhân viên, danh mục gói, đơn mua gói, thu tiền nhiều lần, kỳ hội viên, check-in/out, yêu cầu tập thử và chatbot. Các sửa lỗi thời hạn cuối tháng, lưu thời hạn lúc đặt và danh sách lượt chưa đóng đã có kiểm thử.

Ứng dụng đang dùng `users`, `gym_packages`, `membership_orders`, `memberships` và các bảng phụ trợ Laravel. Chỉ mở thêm trang sản phẩm sẽ chưa đáp ứng yêu cầu giữ SQL gốc. Phải chuyển các nghiệp vụ tương ứng sang `KHACHHANG`, `NHANVIEN`, `GOITAP`, `HOADON`, `DANGKYGOITAP`, `CHECKIN` và những bảng còn lại trong SQL.

Hướng chuyển đổi:

1. Dựng bản dữ liệu chuyển đổi riêng để thử; giữ database `qly_phonggym_laravel` hiện tại làm nguồn trước khi chuyển.
2. Database đích thực tế là `qly_phonggym_laravel_sql`, giữ tên bảng, tên cột, khóa, các procedure/function/trigger từ SQL. Nếu database tên đó đã tồn tại, kiểm tra và thống nhất việc sử dụng trước khi nhập; không chạy lệnh xóa database.
3. Truy cập bảng/khóa chính tiếng Việt viết hoa bằng query builder và các service hiện có, giữ controller/service/repository/policy làm nơi kiểm tra nghiệp vụ.
4. Giữ bảng hạ tầng Laravel cho đăng nhập, session, cache, job, reset mật khẩu. Liên kết tài khoản Laravel với đúng `MAKH` hoặc `MANV`; ID khách và ID nhân viên là hai miền riêng.
5. Chỉ bổ sung bảng phụ cho dữ liệu SQL chưa có: lịch sử thu tiền, điều khoản đã chốt của đơn, yêu cầu mua đang chờ, giữ hàng/chỗ, trạng thái giao hàng, lý do đóng lượt và quyền truy cập. Chốt danh sách migration phụ trước khi viết để tránh lưu trùng nghiệp vụ.
6. Sau chuyển đổi, mỗi giao dịch mới có một nguồn nghiệp vụ chính trong các bảng SQL gốc. Các bảng Laravel cũ được giữ để đối chiếu dữ liệu lịch sử, không duy trì hai hệ thống ghi đơn song song.

Các wrapper gọi procedure dùng cùng kết nối và transaction với thao tác nghiệp vụ liên quan; tham số được bind. Các procedure dùng `MAX(ID) + 1` cần khóa cấp mã dùng chung và xử lý trùng khóa khi nhiều người thao tác. Chưa thay định nghĩa procedure gốc để giải quyết việc này.

## 3. Kiểu chữ và banner

### Kiểu chữ

Font build trước khi sửa chỉ khai báo bộ ký tự Latin và các độ đậm 400/500/600, trong khi giao diện có tiếng Việt và nhiều tiêu đề 700/800. Layout còn thiếu `@fonts`, nên trình duyệt không nạp CSS khai báo font và dùng phông dự phòng. Đã bổ sung chỉ thị này vào layout chung cùng với bộ font tiếng Việt đầy đủ.

Đã dùng **Be Vietnam Pro** thống nhất cho trang khách và nội bộ, gồm bộ Latin, tiếng Việt và độ đậm 400/500/600/700. Các tiêu đề dùng mức 700. Nút, ô nhập, bảng, menu và chatbot dùng cùng hệ font. Trang khách dùng banner, màu nền xanh nhạt, thẻ gói và hình quảng bá từ mẫu gốc; gói năm dùng nền chuyển sắc tím–xanh. Trang đăng nhập chia hai cột; trang nội bộ dùng sidebar tối như `_LayoutStaff.cshtml` và `_LayoutAdmin.cshtml`. Trên màn hình nhỏ, menu thu gọn và biểu mẫu đăng nhập được đưa lên trước.

Font được phục vụ cùng ứng dụng; kiểm tra cả khi mạng bên ngoài không sẵn sàng. Nguồn xác minh hỗ trợ tiếng Việt: [Google Fonts – Be Vietnam Pro](https://github.com/google/fonts/blob/main/ofl/bevietnampro/METADATA.pb).

### Banner

- Tự chuyển ảnh theo vòng sau khoảng 5 giây, chuyển nhẹ, không có hai nút Trước/Sau.
- Tạm dừng khi người dùng tương tác vào vùng banner hoặc tab không hiển thị; tôn trọng thiết lập giảm chuyển động của thiết bị.
- Có cách tạm dừng tự động phù hợp bàn phím và cảm ứng; không thêm lại hai nút điều hướng đã yêu cầu bỏ.
- Không tạo nhiều bộ đếm khi khởi tạo lại trang; ảnh lỗi không làm banner trống hoặc đứng toàn bộ giao diện.
- Banner chỉ nằm trên trang chủ như hiện tại.

## 4. Ma trận bao phủ toàn bộ 22 bảng

| Bảng SQL giữ nguyên | Chức năng/màn hình phải có | Người sử dụng |
| --- | --- | --- |
| `CHUCVU` | Danh mục Quản lý, Lễ tân, PT, HLV lớp; gán chức vụ cho nhân viên | Quản lý |
| `LOAIKHACHHANG` | Hạng khách, mức ưu đãi, gán hạng, hiển thị ưu đãi trên đơn | Quản lý, khách |
| `CHUYENMON` | Danh mục chuyên môn dùng chung cho PT và lớp học | Quản lý |
| `GOITAP` | Danh mục, chi tiết, giá, thời hạn và đăng ký gói; giữ quy tắc cuối tháng đã sửa | Khách, nội bộ |
| `LOAISANPHAM` | Bốn nhóm: thực phẩm bổ sung, quần áo, phụ kiện, dụng cụ nhỏ | Khách, quản lý |
| `KHACHHANG` | Danh sách/tìm kiếm khách, hồ sơ, hạng, tài khoản và lịch sử mua/tập | Khách, nội bộ có quyền |
| `NHANVIEN` | Tạo/sửa tài khoản, chức vụ, hồ sơ, trạng thái truy cập qua phần bổ sung | Quản lý, nhân viên |
| `SANPHAM` | Danh sách/chi tiết; giá gốc, giá khuyến mãi, tồn, hãng, xuất xứ, bảo hành, mô tả | Khách, quản lý |
| `LOPHOC` | Danh sách/chi tiết lớp, học phí, thời gian khóa, số buổi, sĩ số tối đa | Khách, quản lý |
| `CHECKIN` | Ghi giờ vào/ra, lý do từ chối; xử lý lượt quên đóng, lịch sử riêng của khách | Lễ tân, khách |
| `DIACHI` | Thêm/sửa/xóa địa chỉ chưa bị ràng buộc, chọn địa chỉ mặc định, chọn khi giao hàng; giữ tọa độ nếu có | Khách |
| `HOADON` | Hóa đơn chung cho sản phẩm và dịch vụ; tiền hàng, giảm giá, thành tiền, thanh toán, tệp và chữ ký | Khách, thu ngân, quản lý |
| `LOGINHISTORY` | Lịch sử đăng nhập, IP, thời gian, kết quả; hỗ trợ khôi phục có mã OTP và thời hạn | Chủ tài khoản, quản lý có quyền |
| `HINHANH` | Album sản phẩm, một ảnh đại diện, thêm/đổi/gỡ ảnh, ảnh thay thế khi thiếu | Khách, quản lý |
| `CHITIETGIOHANG` | Thêm/sửa số lượng/xóa sản phẩm, gói hoặc lớp; tính lại giá ở server khi đặt | Khách |
| `DANGKYGOITAP` | Kỳ gói đã được duyệt, thời hạn, trạng thái và gia hạn; phân biệt tập thử với mua gói | Khách, nội bộ |
| `DANGKYLOP` | Đăng ký lớp, kiểm tra chỗ, danh sách học viên, trạng thái hủy/kết thúc | Khách, lễ tân, HLV lớp |
| `DANGKYPT` | Đăng ký PT, số buổi, giá mỗi buổi, tổng tiền, buổi còn lại và trạng thái | Khách, PT, nội bộ |
| `LICHTAPPT` | Đặt/xếp lịch PT, kiểm tra trùng PT và khách, xác nhận buổi đã tập | Khách, PT, lễ tân |
| `LICHLOP` | Lịch từng buổi, giáo viên, thời gian; kiểm tra trùng và đánh dấu đã diễn ra | Khách, HLV lớp, quản lý |
| `CHITIETHOADON` | Chi tiết đúng sản phẩm hoặc đăng ký gói/lớp/PT, số lượng, đơn giá; xem/in hóa đơn | Khách, nội bộ |
| `NHANVIEN_CHUYENMON` | Một HLV/PT có nhiều chuyên môn; lọc và phân công đúng chuyên môn | Quản lý |

Giữ chức vụ SQL và quyền thao tác web là hai khái niệm liên quan nhưng khác nhau. HLV/PT chỉ xem khách và lịch được phân công; lễ tân xử lý các nghiệp vụ được cấp; quyền quản lý không được suy ra chỉ từ tên đăng nhập.

## 5. Luồng cửa hàng và giao hàng

1. Khách xem/tìm/lọc sản phẩm theo nhóm, hãng và giá; xem album, giá khuyến mãi, tồn, mô tả và bảo hành. Thêm mục Sản phẩm và Giỏ hàng vào điều hướng; thêm sản phẩm nổi bật trên trang chủ.
2. Khách đăng nhập để giữ giỏ; thêm sản phẩm hoặc dịch vụ được hỗ trợ trong `CHITIETGIOHANG`. Giới hạn số lượng nguyên dương, không bán quá tồn. Quần áo trong SQL đã có màu/size ở tên sản phẩm; đợt này quản lý theo từng mã sản phẩm, chưa mở hệ thống biến thể riêng.
3. Khi đặt, server chốt tên hàng, giá, ưu đãi hạng khách, số lượng và điều khoản dịch vụ. Quy tắc đề xuất: dùng giá khuyến mãi hợp lệ thay giá gốc, sau đó áp dụng phần trăm hạng khách; lưu toàn bộ kết quả vào điều khoản đơn. `GIAMGIA` trên hóa đơn là số tiền đã giảm, không nhập nhằng với phần trăm.
4. Chọn Nhận tại quầy hoặc Giao hàng. Giao hàng bắt buộc có địa chỉ; lưu bản chụp địa chỉ/người nhận vào thông tin giao hàng để sửa sổ địa chỉ sau này không làm đổi đơn cũ. Phí giao hàng hiển thị rõ và được chốt trước khi khách xác nhận; chưa tích hợp hãng vận chuyển.
5. Giữ hàng/chỗ cho đơn chờ bằng dữ liệu bổ sung, có thao tác nhân viên giải phóng khi hủy. SQL hiện không có quy định hết hạn đơn; chưa tự thêm cơ chế hủy đơn có tiền.
6. Nhân viên ghi nhận khoản thu và xác nhận khi đủ tiền. Việc kích hoạt gói/lớp/PT, tạo chi tiết chứng từ và chốt tồn chạy trong transaction; xác nhận lặp không tạo đăng ký hoặc trừ kho lần hai.
7. Hàng nhận tại quầy: Chờ chuẩn bị → Sẵn sàng nhận → Đã nhận. Giao hàng: Chờ chuẩn bị → Đang giao → Đã giao; có thông tin người nhận và ghi chú xử lý giao không thành công. Trạng thái thanh toán được giữ độc lập.
8. Khách theo dõi đơn và hóa đơn; nhân viên tìm/lọc theo mã, khách, trạng thái tiền, nhận tại quầy/giao hàng. Quản lý xem số bán, tồn, doanh thu sản phẩm và dịch vụ riêng.

### Tồn kho và trigger bắt buộc

Giữ `trg_CapNhatSoLuongTon`. Trigger này trừ tồn ngay khi thêm dòng sản phẩm vào `CHITIETHOADON`, không kiểm tra hóa đơn đã trả tiền hay chưa.

Vì vậy, các dòng yêu cầu mua chưa chốt nằm ở phần bổ sung; chỉ ghi dòng chứng từ sản phẩm vào `CHITIETHOADON` tại bước chốt. Laravel kiểm tra/khóa tồn và số đang giữ trước bước đó. Trigger là nơi trừ tồn thực tế, không trừ lại trong PHP. Hủy đơn chưa chốt giải phóng phần giữ hàng. Điều chỉnh tồn thủ công phải ghi lý do/người xử lý; không xóa lịch sử hóa đơn để làm thay đổi tồn.

## 6. Lớp học, PT và tập thử

- **Lớp học:** quản lý tạo khóa, học phí, số buổi và giới hạn học viên; phân HLV đúng chuyên môn; xếp từng buổi; kiểm tra số chỗ và thời gian trùng. Chỉ kích hoạt đăng ký khi thanh toán được xác nhận. Khách xem lịch và đăng ký của mình; HLV xem danh sách lớp được giao.
- **PT:** khách chọn PT/chuyên môn và số buổi; chốt giá mỗi buổi và tổng tiền. Xếp lịch không trùng PT/khách, kiểm tra buổi còn lại. Xác nhận Đã tập chỉ tiêu thụ một buổi, thao tác lặp không trừ thêm. Kết thúc khi hoàn thành hợp đồng; mọi đổi/hủy lịch có trạng thái và dấu vết.
- **Tập thử:** giữ biểu mẫu công khai chỉ tạo yêu cầu. Bổ sung bước nhân viên Duyệt tập thử gọi `SP_DANGKYTAPTHU`; khác với chỉ Đánh dấu đã liên hệ. Đăng ký tạo ra được đánh dấu nguồn tập thử trong phần bổ sung, có thời hạn thử riêng, không bị hiểu thành gói đã thanh toán.
- Procedure thử hiện dùng thời hạn theo ngày và có mật khẩu mặc định khi tạo khách mới. Chỉ gọi sau xác minh của nhân viên, với gói/thời lượng thử được giới hạn; thay mật khẩu mặc định bằng giá trị an toàn trước khi hoàn tất transaction, cho khách thiết lập mật khẩu qua quy trình tài khoản. Không công khai một endpoint gọi thẳng procedure này.
- Quyền vào phòng tập dựa trên gói hợp lệ hoặc buổi thử đã được duyệt; quyền tham dự lớp/PT được kiểm tra theo đăng ký và lịch tương ứng. Những lịch/gói từ năm 2025 trong SQL được giữ là lịch sử, không tự coi là còn hiệu lực trong năm 2026.

## 7. Bao phủ 11 procedure, function và trigger

Giữ nguyên tên và chữ ký/định nghĩa gốc trong bộ SQL; Laravel bổ sung kiểm tra ở phía gọi. Nếu có yêu cầu sửa định nghĩa SQL để khắc phục giới hạn của bản gốc, trình riêng trước khi thay đổi.

| Đối tượng SQL | Cách sử dụng trong Laravel và kiểm tra cần có |
| --- | --- |
| `SP_KHACHHANGDANGKY` | Đăng ký khách; kiểm tra dữ liệu/trùng định danh, khóa cấp mã, truyền mật khẩu đã băm; liên kết với tài khoản Laravel trong transaction |
| `sp_KhachHangLogin` | Laravel xác minh mật khẩu bằng Hash; sau đó gọi procedure với giá trị băm đã xác minh để lấy đúng khách, không so mật khẩu thô hoặc bỏ qua bước Hash |
| `SP_KHOIPHUCMATKHAU` | Chỉ gọi sau khi Laravel xác minh token/OTP còn hạn, giới hạn lần thử; truyền mật khẩu mới đã băm; đồng bộ tài khoản liên kết |
| `sp_LayChiTietHoaDon` | Dùng các result set để lấy đầu/chi tiết hóa đơn; kiểm tra chủ sở hữu trước khi trả kết quả; bổ sung tên hợp đồng PT và điều khoản đơn còn chờ ở lớp trình bày |
| `SP_GRANT_ROLE_TO_USER` | Trang quản trị MySQL riêng: cấp role trong danh sách được phép cho tài khoản nằm trong phạm vi quản lý |
| `GET_SQL_USERS` | Liệt kê tài khoản MySQL thuộc phạm vi quản lý, trạng thái khóa; không dùng trường password_last_changed của procedure làm thời điểm đăng nhập thật |
| `SP_CREATE_MANAGER_USER` | Tạo tài khoản MySQL trong khu vực quản trị được phân quyền riêng, không nhầm với tạo tài khoản nhân viên đăng nhập website |
| `SP_LOCK_USER` | Khóa tài khoản MySQL thuộc phạm vi cho phép; ghi người thực hiện và kết quả |
| `SP_UNLOCK_USER` | Mở khóa tài khoản MySQL thuộc phạm vi cho phép; ghi người thực hiện và kết quả |
| `SP_GET_AUDIT_LOGS` | Tìm lịch sử đăng nhập theo username trong một giờ như procedure; nhật ký khác là phần bổ sung, không giả rằng tham số tên bảng của procedure đã lọc được mọi thao tác |
| `SP_DANGKYTAPTHU` | Bước duyệt tập thử của nhân viên, có bảo vệ danh tính/mật khẩu và phân biệt quyền thử với gói trả tiền |
| `f_encryptAddCipher` | Giữ để các procedure gốc chạy được. Hàm hiện trả nguyên đầu vào; việc băm mật khẩu được thực hiện trong Laravel trước khi gọi |
| `trg_CapNhatSoLuongTon` | Giữ làm nơi trừ tồn thực tế khi ghi chi tiết hóa đơn sản phẩm; kiểm tra âm tồn và xác nhận lặp bằng test |

Các procedure quản trị MySQL dùng kết nối quản trị tách khỏi kết nối web thông thường. Chỉ quản trị viên được chỉ định mới truy cập; giới hạn tài khoản được tạo/quản lý, chặn root/tài khoản hệ thống/tài khoản kết nối chính. Quyền MySQL và quyền nhân viên web được hiển thị ở hai khu vực riêng để tránh cấp nhầm.

## 8. Hóa đơn, chữ ký và lịch sử

- Một hóa đơn thể hiện đúng từng loại hàng/dịch vụ, giá đã chốt, giảm giá và số tiền cuối; danh sách tìm/lọc/phân trang. Dùng `HOADON` và `CHITIETHOADON` gốc, giữ thu tiền từng phần và xác nhận lặp an toàn của Laravel.
- Xuất tệp hóa đơn, tạo chữ ký và kiểm tra chữ ký; sử dụng `FILE_PATH`, `SIGNATURE_PATH`, `PUBLIC_KEY_USED`, `SIGNATURE_VERIFIED`, `VERIFIED_AT` như trong SQL. Khóa riêng và tài liệu nội bộ không đặt trong thư mục public.
- Trạng thái chữ ký dựa trên kiểm tra nội dung tệp thực tế. Thay đổi nội dung sau ký phải báo không hợp lệ; không chỉ bật một cờ trên giao diện.
- File SQL có các đường dẫn PDF/chữ ký nhưng không kèm chính những tệp đó; dữ liệu cũ thiếu tệp hiển thị Chưa có tệp/chưa xác minh. Không gán nhãn hợp lệ cho các giá trị cũ chỉ vì có tên khóa.
- `LOGINHISTORY` ghi đăng nhập thành công/thất bại; khôi phục có hạn dùng và chống dùng lại. Khi bổ sung OTP để bao phủ trường mã/hạn dùng, mã xác minh không xuất hiện trên màn hình audit và không thay thế kiểm tra quyền sở hữu tài khoản.

## 9. Chuẩn bị và chuyển dữ liệu

1. Tạo bản sao nguồn để nhập thử, thống kê số bản ghi từng bảng và báo cáo đối chiếu trước/sau. File SQL gốc có `DROP DATABASE IF EXISTS ql_gym`; tuyệt đối không chạy nguyên khối vào database đang có dữ liệu cần giữ.
2. Kiểm tra thứ tự khóa ngoại, trùng mã, liên kết hóa đơn/chi tiết/đăng ký, giá/tổng tiền/giảm giá và tồn. Dữ liệu giao dịch cũ bất thường được đưa vào danh sách đối soát; không tự sửa tổng tiền để che chênh lệch.
3. Lập bảng ánh xạ ID cho dữ liệu Laravel hiện tại và dữ liệu SQL, kiểm tra username/email/điện thoại trùng hoặc xung đột vai trò trước khi gộp. Không ghi đè tài khoản theo tên đăng nhập một cách mù quáng.
4. Chuẩn hóa mật khẩu cũ sang dạng băm khi nhập; giữ quy tắc đồng bộ cho các thao tác đi qua procedure. File SQL gốc vẫn được giữ làm tài liệu nguồn.
5. Kiểm tra và bổ sung ảnh đúng sản phẩm. Ví dụ dữ liệu ảnh cho mã sản phẩm 58 có tên ảnh legging trong khi sản phẩm 58 là xà đơn; cần đối soát chứ không chỉ kiểm tra mã tồn tại. Không có bộ ảnh sản phẩm thực tế đi kèm trong phần public đã rà; dùng ảnh thay thế có nhãn cho tới khi có ảnh đúng.
6. Kiểm tra phân công chức vụ: tài khoản tên `admin` ở cuối SQL mang mã chức vụ 2 (PT), không tự nâng thành quản lý chỉ vì tên là admin. Chức vụ/quyền phải được gán theo dữ liệu và quyết định người quản lý.
7. Giữ các lịch năm 2025 là lịch sử; thêm bộ dữ liệu demo hiện hành riêng cho lớp, PT, mua hàng và giao hàng. Không sửa ngày cũ hàng loạt để làm chúng thành lịch đang hoạt động.
8. Khi bản thử đạt, chuyển cấu hình Laravel sang database đích và kiểm tra lại đường dẫn, ảnh, tệp, đăng nhập, số dư/đơn, quyền nhân viên. Chỉ đánh dấu hoàn tất khi đối chiếu dữ liệu đạt.

## 10. Thứ tự triển khai và điều kiện hoàn tất từng đợt

| Đợt | Công việc | Điều kiện bàn giao |
| --- | --- | --- |
| 1 | Kiểu chữ và banner | Font tiếng Việt đồng bộ trên trang khách/nội bộ; tự chuyển, không còn Trước/Sau, kiểm tra mobile và giảm chuyển động |
| 2 | Database SQL gốc, ánh xạ tài khoản và nghiệp vụ đang có | Đủ 22 bảng/11 procedure/function/trigger; dữ liệu đối chiếu được; gói/thu tiền/check-in cũ vẫn chạy và test hồi quy đạt |
| 3 | Danh mục sản phẩm, ảnh, tồn, địa chỉ | Khách xem/lọc/chi tiết; quản lý sửa danh mục và tồn; khách quản lý địa chỉ; không có ảnh đại diện mâu thuẫn |
| 4 | Giỏ, hóa đơn, thu tiền, nhận tại quầy/giao hàng | Mua được vật phẩm; giá server, giảm giá rõ; không âm/trừ hai lần tồn; theo dõi cả hai cách nhận hàng |
| 5 | Chức vụ, hạng khách, chuyên môn, lớp và PT | Đăng ký, thanh toán, xếp lịch, sĩ số/số buổi và phân quyền chạy xuyên suốt |
| 6 | Duyệt tập thử, chữ ký hóa đơn, lịch sử và quản trị MySQL | Mỗi procedure có luồng gọi thực được kiểm chứng; không bỏ sót module đặc thù của SQL |
| 7 | Kiểm thử toàn bộ, dữ liệu demo, hướng dẫn chạy/nghiệm thu | Có checklist đối chiếu 22 bảng và 13 đối tượng SQL; đủ tài khoản demo từng vai trò; build/test và thao tác trình duyệt đạt |

Không coi hoàn thành đợt cửa hàng là đã đủ SQL; còn phải hoàn tất lớp/PT, chữ ký và các procedure quản trị. Không hứa thời gian cố định trước khi bản nhập dữ liệu và yêu cầu chấm về các đối tượng SQL được kiểm chứng.

## 11. Các ca kiểm thử bắt buộc

- Font không trộn kiểu ở chữ có dấu; 400/500/600/700 thực sự được tải; giao diện 360/768/1440px không vỡ.
- Banner chạy nhiều vòng, bỏ đúng hai nút, tạm dừng được và không tạo timer trùng.
- Hai khách mua sản phẩm cuối cùng đồng thời: không bán/trừ kho vượt số có thể bán. Xác nhận lại cùng đơn không trừ thêm kho hoặc tạo thêm đăng ký.
- Sửa giá sản phẩm/thời hạn gói/ưu đãi hạng sau khi đặt không làm đổi chứng từ đã chốt.
- Đơn có tiền một phần không bị hủy tùy tiện; không kích hoạt dịch vụ khi chưa đủ tiền.
- Địa chỉ của khách A không dùng/sửa được bởi khách B; sửa địa chỉ sổ tay không làm đổi địa chỉ trên đơn đã đặt.
- Trạng thái nhận/giao hàng không tự làm hóa đơn thành đã trả tiền; chỉ người có quyền được cập nhật.
- Lớp vừa hết chỗ, hai đăng ký đồng thời, HLV/khách trùng lịch, PT dùng hết buổi, ghi Đã tập lặp đều có test.
- Tập thử công khai không tự cấp quyền; duyệt thử đi qua procedure được kiểm soát và không để mật khẩu mặc định có thể sử dụng.
- Đúng ngày cuối tháng/năm nhuận, gói tương lai/hết hạn, lượt chưa check-out cũ vẫn có test hồi quy.
- Hóa đơn khác chủ bị chặn; chữ ký đúng đạt, tệp bị sửa hoặc thiếu phải báo đúng trạng thái.
- Các thao tác quản trị MySQL bị chặn với nhân viên thường; không khóa/cấp quyền cho tài khoản hệ thống; thử trên MySQL riêng, không làm thử với root đang dùng.
- Bộ kiểm thử hiện có 99 test PHP và 8 test JavaScript là nền hồi quy; bổ sung test endpoint/SQL integration cho từng module mới, không chỉ test trang có HTTP 200.

## 12. Kết quả triển khai và giới hạn dữ liệu nguồn

Đã nhập đủ 57 sản phẩm và tất cả 35 đối tượng SQL trên bản sao database; file gốc và thư mục QL_GYM không bị chỉnh sửa. Có màn hình nghiệp vụ tương ứng cho toàn bộ 22 bảng trong ma trận và màn hình quản trị đọc dữ liệu để đối chiếu. Các wrapper sử dụng 11 procedure gốc, giữ function và trigger gốc. Mật khẩu nguồn được băm; f_encryptAddCipher gốc chỉ trả lại đầu vào nên không được dùng thay cho xác minh mật khẩu của Laravel.

Đã kiểm thử bán hàng, khuyến mãi/hạng khách, giữ tồn/giải phóng tồn, thanh toán từng phần/chốt lặp, quyền sở hữu địa chỉ/đơn/giỏ, mốc cuối tháng, lịch PT/lớp, OTP, tài khoản bị khóa, ký thật và phát hiện sửa tệp. Các procedure tạo/cấp role/khóa/mở khóa tài khoản đã được chạy trên MySQL riêng cổng 3318, sau đó dừng server kiểm thử. Trang bán hàng và trang quản lý được kiểm tra bằng Edge, gồm màn hình điện thoại không tràn ngang và không có lỗi JavaScript.

Dữ liệu cần đối chiếu: hóa đơn #182 và #138 thiếu chi tiết nên không cho thu/chốt/ký; các tệp PDF/chữ ký lịch sử trong SQL không đi kèm nên không giả lập là tệp đã có. Chứng từ mới dùng HTML ký RSA-SHA256 và có thể in PDF từ trình duyệt. Một số ảnh sản phẩm gốc sai loại hàng; giữ các bản ghi nhưng hiển thị ảnh chờ cập nhật, có chức năng tải ảnh đúng. Các lớp năm 2025 giữ nguyên lịch sử, quản lý tạo lớp hiện hành khi sử dụng. Không tự thêm hàng mẫu, đổi giá hay dịch chuyển ngày lịch cũ.

Cách chạy, cấu hình, tài khoản local và các giới hạn được cập nhật trong README ở thư mục gốc.

## 13. Hoàn thiện vận hành sau tích hợp SQL

Đã bổ sung hạn đơn, giải phóng hàng/chỗ khi đơn chưa thu tiền hết hạn; bảo vệ khoản thu đầu tiên sát/quá hạn; thông báo trong web cho giao dịch/lịch, nhắc gói/buổi và đọc theo quyền sở hữu. Email nghiệp vụ giữ tắt theo lựa chọn người dùng, nhưng có đường gửi SMTP, giới hạn thử lại và theo dõi lỗi khi bật sau này.

Có báo cáo theo khoảng ngày và xuất CSV, đổi mật khẩu hiện tại/đóng session khác, phí giao hàng và thông tin thanh toán cấu hình được, lịch tuần/tháng, các trang liên hệ/hướng dẫn/chính sách và bản dịch phân trang. Thêm màn hình đối chiếu dữ liệu thiếu, không tự tạo ảnh/chứng từ/giá giả hoặc sửa lịch sử.

Phần mở rộng chỉ dùng hai bảng phụ `gym_settings`, `gym_messages` và hai cột trên `gym_fulfillments`; 35 đối tượng SQL gốc được giữ nguyên. Đã sao lưu trước migration. Scheduler local chạy mỗi phút; hướng dẫn chạy và khởi động lại có trong README. Thông tin phòng gym/ngân hàng chờ chủ phòng gym điền tại màn hình cấu hình.

## 14. Xác minh sau khi bật MySQL — 21/10/2026

- Tạo backup MySQL trước migration tại `C:\laragon\tmp\qly_phonggym_laravel-pre-integration-20261021.sql`.
- Áp dụng thành công ba migration còn chờ lên `qly_phonggym_laravel`; các bảng Laravel đang có được giữ nguyên.
- Xác minh đủ 22 bảng SQL gốc, 11 stored procedure, 1 function, 1 trigger; dữ liệu nguồn có 57 sản phẩm và 20 lớp.
- `php artisan test --no-coverage`: 170 test đạt, 1 test bỏ qua có chủ đích, 1.167 assertions. Test bị bỏ qua chỉ thao tác tạo/khóa tài khoản MySQL và chỉ chạy khi cấu hình một máy MySQL kiểm thử cô lập ở cổng 3318.
- Kiểm tra 131 cặp method/URI không trùng; trang chủ, cửa hàng, lớp học và huấn luyện viên đã được mở ở preview trước khi chuyển sang database ứng dụng.
- Toàn bộ lịch lớp từ SQL mẫu hiện là lịch lịch sử kết thúc trong năm 2025; quản lý cần tạo lịch mới để mở đăng ký. Hồ sơ PT mẫu chưa có chuyên môn/đăng ký mở, nên trang khách hướng người dùng liên hệ quầy. Không tự dịch chuyển ngày hoặc sửa dữ liệu mẫu gốc.
- Trình duyệt trong Codex chặn công cụ mở lại địa chỉ loopback `127.0.0.1:8002` ở lượt xác minh cuối. Laravel server đã được khởi chạy tại cổng 8002 để chủ dự án mở trực tiếp; không tuyên bố đã kiểm tra lại giao diện trên database ứng dụng bằng trình duyệt.

## 15. Kiểm thử hồi quy toàn bộ và sửa chức vụ Admin — 21/10/2026

- Chạy lại `php artisan test --no-coverage`: 171 test đạt, 1 test bỏ qua có chủ đích, 1.170 assertions; `vendor/bin/pint --dirty --format agent` đạt.
- RAG: 20 unit test đạt. Dựng API local bằng cấu hình sẵn có; health sẵn sàng và chatbot trên trình duyệt trả lời câu hỏi về thời hạn gói 1 tháng, kèm nguồn tài liệu.
- Mở trực tiếp trang chủ/gói tập, cửa hàng và lọc Protein, lớp học và lọc Yoga, danh sách PT, chatbot tại `http://127.0.0.1:8002`.
- Tài khoản Admin mẫu trong file SQL bị gán nhầm mã chức vụ PT. Sửa seed thành mã Quản lý và thêm migration có điều kiện chỉ sửa đúng bản ghi Admin mẫu; test hồi quy xác nhận danh sách PT không có Admin. Đã áp dụng migration lên database local và kiểm tra lại trang PT.
- Lịch lớp nhập từ dữ liệu mẫu đã kết thúc năm 2025; hồ sơ PT còn thiếu chuyên môn nên chưa mở đăng ký trực tuyến. Đây là phần dữ liệu cần quản lý cập nhật trước khi vận hành công khai.
