# Minh bạch giảm giá trên giỏ hàng và hóa đơn — kế hoạch triển khai

**Mục tiêu:** Khách và nhân viên nhìn được đơn giảm vì đâu, theo mức nào; hóa đơn mới giữ nguyên căn cứ tính giá tại thời điểm đặt dù bảng giá hoặc hạng khách thay đổi sau đó.

**Cách làm:** Dùng hai cơ chế đang có: giá khuyến mãi của từng sản phẩm và phần trăm ưu đãi theo hạng khách. Giỏ hàng và thao tác đặt đơn dùng chung một cách tính trên máy chủ. Lưu giá gốc của dòng sản phẩm và bản ghi ưu đãi hạng cùng hóa đơn. Dữ liệu cũ thiếu căn cứ được hiển thị là chưa xác định nguồn, không tự suy diễn từ tỷ lệ phần trăm.

**Công nghệ:** Laravel, Blade, MySQL, PHPUnit. Chỉ áp dụng migration bổ sung sau khi sao lưu; kiểm thử ghi dữ liệu trên database QA riêng.

**Trạng thái triển khai — 28/10/2026:** Đã triển khai chính sách và các luồng dưới đây. Trên `qly_phonggym_test`, nhóm mới đạt **9/9 test, 67 assertions**; toàn bộ PHP suite đạt **272/273 test, 1 skipped, 1,881 assertions**. Migration được chạy trong DB test; chưa chạy trên DB ứng dụng. Xem kết quả và giới hạn nghiệm thu tại `laravel/docs/test-plan.md`.

## Hiện trạng đã kiểm tra

- `laravel/app/Services/GymCommerceService.php`: sản phẩm dùng `GIAKHUYENMAI` làm đơn giá khi thấp hơn giá gốc. Khi đặt đơn, hệ thống lấy `LOAIKHACHHANG.MUCGIAM`, tính `intdiv(TONGTIEN × phần trăm, 100)` và ghi tổng giảm vào `HOADON.GIAMGIA`.
- `laravel/resources/views/gym/cart.blade.php`: giỏ đã ghi tên hạng và phần trăm, nhưng dòng sản phẩm chưa giải thích giá gốc/giá khuyến mãi.
- `laravel/resources/views/gym/invoice.blade.php` và `laravel/resources/views/gym/invoice-document.blade.php`: chỉ in một dòng “Giảm giá”; chưa lưu hoặc trình bày nguồn và mức giảm đã áp dụng.
- `laravel/database/migrations/2026_10_21_183807_integrate_original_gym_sql.php`: `gym_invoice_lines` lưu đơn giá đã bán, chưa lưu giá gốc. Sửa giá sản phẩm hoặc hạng khách sau khi đặt khiến không thể dựng lại căn cứ tính tiền từ dữ liệu hiện tại.
- Màn quản lý đã có **Hạng khách hàng** với trường phần trăm và màn sản phẩm đã có **Giá khuyến mãi**. Chưa cần một màn tạo mã giảm giá để giải thích hai cơ chế này.
- `laravel/resources/js/checkout.js` chỉ cập nhật phí giao và tổng tiền đang xem trên trình duyệt; giá, hạng khách hoặc phí giao có thể đổi trước khi người mua bấm đặt. Hiện bước đặt sẽ tính lại nhưng chưa yêu cầu khách xác nhận số tiền mới.
- `laravel/app/Http/Controllers/MembershipOrderController.php::store` đã chuyển người mua gói về giỏ chung. `membership_orders` cũ và hóa đơn do `GymIdentityService::recordMembership` sinh ra là dữ liệu lịch sử riêng; không được suy ra rằng chúng từng hưởng ưu đãi hạng.
- `GymAdministrationController` đã ghi sự kiện tạo/sửa danh mục, nhưng nhật ký sửa giá khuyến mãi và phần trăm hạng chưa chứa giá trị trước–sau để đối chiếu.
- Thủ tục `SP_KHACHHANGDANGKY` trong `laravel/database/sql/ql_gym_mysql.sql` tự xếp người có `YEAR(NOW()) - YEAR(NGAYSINH) < 24` vào hạng “Học sinh, Sinh viên” 5%, không xác minh tình trạng học. Cách trừ năm có thể sai quanh sinh nhật. Hồ sơ cho phép sửa ngày sinh nhưng luồng đồng bộ hồ sơ không đồng bộ lại `MALOAIKH`, nên quy tắc hiện tại cũng không nhất quán theo thời gian.

## Quy tắc tiền phải giữ nguyên

1. `giá dòng = đơn giá đang bán × số lượng`; đơn giá đang bán là giá khuyến mãi hợp lệ hoặc giá gốc. Tổng các dòng này là `HOADON.TONGTIEN`.
2. `giảm theo hạng = intdiv(HOADON.TONGTIEN × tỷ lệ hạng, 100)`. Phí giao hàng không thuộc cơ sở giảm.
3. `HOADON.THANHTIEN = HOADON.TONGTIEN − HOADON.GIAMGIA + phí giao hàng`.
4. Khoản tiết kiệm do **giá khuyến mãi sản phẩm** chỉ là thông tin so với giá gốc; giá khuyến mãi đã nằm trong `TONGTIEN`, nên không được trừ thêm lần nữa.
5. Giá, tên hạng và tỷ lệ là ảnh chụp tại lúc đặt. Không dùng giá/hạng hiện tại để viết lại hóa đơn cũ.
6. Giữ đúng phạm vi hiện hành: ưu đãi hạng áp dụng trên **giá bán sau khuyến mãi** của cả sản phẩm, gói và lớp; phí giao hàng nằm ngoài phạm vi. Hai loại ưu đãi có thể cùng xuất hiện, nhưng tiết kiệm do giá sản phẩm không đi vào `HOADON.GIAMGIA`.
7. Giá khuyến mãi sản phẩm có hiệu lực ngay với đơn đặt **sau** khi lưu thay đổi; đơn đã đặt dùng ảnh chụp giá cũ. Nêu quy tắc này tại màn quản lý giá và giỏ hàng.

## Chính sách đủ điều kiện hạng đã áp dụng

Hạng “Học sinh, Sinh viên” yêu cầu quản lý xác minh trực tiếp trước khi áp ưu đãi. Không lưu ảnh giấy tờ cá nhân trên website. Thủ tục đăng ký mới mặc định vào “Gym Null”, không tự cấp hạng từ ngày sinh. Khách hiện đang ở hạng học sinh/sinh viên không được giảm trên đơn mới cho đến khi quản lý xác minh; migration không sửa hàng loạt hạng khách hay hóa đơn cũ. Gán lại hạng sẽ thu hồi xác minh còn hiệu lực để tránh mang theo quyền giảm sai.

Giá khuyến mãi sản phẩm chỉ hợp lệ khi lớn hơn 0 và thấp hơn giá gốc. Mức giảm hạng mới giới hạn từ 0% đến 99%; giá bán thông thường phải lớn hơn 0. Các dòng dữ liệu cũ có giá khuyến mãi 0 được hiển thị/tính như giá gốc, không phải mặt hàng miễn phí.

## Phạm vi triển khai và tiêu chí kiểm tra

### 1. Kiểm soát điều kiện được hưởng ưu đãi hạng

**File:** `laravel/database/sql/ql_gym_mysql.sql`, migration mới cập nhật `SP_KHACHHANGDANGKY`, `laravel/app/Services/GymIdentityService.php`, `laravel/app/Http/Controllers/GymAdministrationController.php`, `laravel/app/Http/Controllers/ProfileController.php`, `laravel/resources/views/profile/show.blade.php`.

1. Thủ tục đăng ký mặc định hạng Gym Null; ngày sinh không được dùng để tự nhận ưu đãi học sinh/sinh viên.
2. Quản lý xác minh/thu hồi tại hồ sơ khách hàng, lý do bắt buộc; lưu quản lý thao tác, thời điểm và lý do. Không tải giấy tờ cá nhân lên web.
3. Không thay hàng loạt phân hạng hiện có; xác minh mới chỉ ảnh hưởng đơn mới, không đổi giá hoặc căn cứ hóa đơn đã tạo.
4. Khi thay hạng của khách, xác minh cũ bị thu hồi.
5. Kiểm thử khách mới, chặn role khách, xác minh/thu hồi, nhật ký và việc chỉ tác động đơn mới.

### 2. Thống nhất cách tính giữa giỏ và đặt đơn

**File:** `laravel/app/Services/GymCommerceService.php`, `laravel/app/Http/Controllers/GymStoreController.php`; kiểm thử trong `laravel/tests/Feature/GymCommerceTest.php`.

1. Tạo một hàm/bộ tính giá dùng chung cho giỏ và bước `checkout`, trả về giá gốc, giá bán, số lượng và tổng của từng dòng; tên hạng, tỷ lệ, cơ sở giảm, số tiền giảm, phí giao hàng, tổng phải trả.
2. Lưu báo giá mà khách vừa xem theo checkout token trong session, gồm danh sách mặt hàng/số lượng, giá bán, hạng và tỷ lệ, cùng phí giao đang cấu hình. Khi khách chọn giao hay nhận tại quầy, dùng đúng phí của lựa chọn đó để so sánh.
3. Khi đặt đơn, đọc lại sản phẩm, hạng khách, phí giao và tồn từ database trong transaction rồi tính lại. Nếu giá/tỷ lệ/phí đã đổi so với báo giá khách xem, dừng trước khi tạo hóa đơn hay giữ hàng, đưa khách về giỏ với tổng mới và thông báo cần xác nhận lại. Không lấy số tiền giảm từ trình duyệt.
4. Giữ quy tắc làm tròn nguyên hiện có (`intdiv`). Xử lý hạng thiếu hoặc mức 0% rõ ràng. Chính sách đề xuất cho đơn mới là không cho cấu hình 100% ưu đãi hạng hoặc giá bán sản phẩm 0đ trong luồng thu tiền thông thường; rà các giá trị đang tồn tại trước khi áp quy tắc để không làm hỏng dữ liệu cũ.
5. Kiểm thử giỏ và hóa đơn cho cùng một số tiền với sản phẩm khuyến mãi, gói tập, đơn hỗn hợp và phí giao hàng; thử đổi giá, hạng, phí giao và số lượng giữa lúc mở giỏ và lúc gửi form.

### 3. Lưu căn cứ giảm giá cho hóa đơn mới

**File:** migration mới trong `laravel/database/migrations/`, `laravel/app/Services/GymCommerceService.php`, `laravel/app/Services/GymPortalService.php`; kiểm thử trong `laravel/tests/Feature/GymCommerceTest.php`.

1. Bổ sung cột `original_unit_price` (nullable) vào `gym_invoice_lines`; khi đặt đơn mới, lưu giá gốc của sản phẩm bên cạnh `unit_price` thực thu. Dòng dịch vụ có thể dùng cùng giá gốc/giá bán hoặc để null nếu không có khuyến mãi.
2. Tạo bảng `gym_invoice_discounts` có `invoice_id`, `source_type = customer_tier`, mã và tên hạng tại lúc đặt, tỷ lệ %, cơ sở tính, số tiền giảm, thời điểm tạo. Đặt ràng buộc duy nhất `(invoice_id, source_type)` để mỗi hóa đơn chỉ có tối đa một bản ghi cho loại ưu đãi này ở giai đoạn hiện tại.
3. Thêm dấu phiên bản ảnh chụp giá cho hóa đơn mới, ví dụ `pricing_snapshot_version` nullable trong `gym_fulfillments`. Hóa đơn trước khi triển khai để null; hóa đơn checkout mới ghi phiên bản hiện hành kể cả khi giảm giá bằng 0. Nhờ đó phân biệt dữ liệu cũ thiếu nguồn với đơn mới bị thiếu bản ghi do lỗi.
4. Ghi hóa đơn, dòng hàng, bản ghi giảm giá, phiên bản ảnh chụp và giao nhận trong cùng transaction. Chỉ ghi bản ghi ưu đãi khi thực sự có giảm; `HOADON.GIAMGIA` vẫn là số tiền kế toán dùng để tính phải trả.
5. Bổ sung kiểm tra nhất quán tại `laravel/app/Services/GymPortalService.php`: hóa đơn có phiên bản ảnh chụp mới phải có bản ghi nguồn tương ứng khi `GIAMGIA > 0`; bản ghi giảm phải khớp tổng, cơ sở tính, tỷ lệ và quy tắc làm tròn. Nếu lệch, đưa hóa đơn vào diện đối soát và chặn thu/chốt/ký theo cơ chế hiện có. Hóa đơn trước phiên bản mới mà thiếu nguồn được hiển thị là chưa xác định, không mặc nhiên xem là lỗi số học.
6. Kiểm thử sau khi quản lý sửa giá sản phẩm hoặc hạng khách, hóa đơn đã đặt vẫn giữ nguyên giá, tỷ lệ và tổng tiền. Thử gửi lại checkout token không được tạo thêm bản ghi giảm giá.

### 4. Hiển thị rõ cho khách, nhân viên và quản lý

**File:** `laravel/resources/views/gym/cart.blade.php`, `laravel/resources/js/checkout.js`, `laravel/app/Http/Controllers/GymInvoiceController.php`, `laravel/app/Services/GymInvoiceDocumentService.php`, `laravel/resources/views/gym/invoice.blade.php`, `laravel/resources/views/gym/invoice-document.blade.php`.

1. Ở giỏ hàng, dòng sản phẩm khuyến mãi hiển thị giá gốc gạch ngang, giá đang bán và nhãn “Giá khuyến mãi”. Tóm tắt đơn hiển thị “Ưu đãi hạng [tên] ([x]%)” và số tiền trừ; phần tiết kiệm sản phẩm, nếu trình bày, phải ghi “đã tính trong giá bán”.
2. Ở hóa đơn, đọc bản ghi ưu đãi đã lưu thay vì tra hạng khách hiện tại. Hiển thị tên hạng, tỷ lệ, cơ sở tính và số tiền giảm; dòng sản phẩm hiện giá gốc và giá đã bán từ ảnh chụp của dòng hàng.
3. Áp dụng cách trình bày đó cho chứng từ HTML được tạo/ký sau khi triển khai; truyền ảnh chụp ưu đãi từ `GymInvoiceDocumentService` vào template. Chứng từ cũ đã ký giữ nguyên nội dung và chữ ký; việc hiển thị mới trên trang không được làm thay đổi file đã ký.
4. Dùng cùng dữ liệu và cùng nhãn khi hội viên, nhân viên và quản lý mở một hóa đơn. Không thêm nút cho phép nhân viên tự sửa tiền giảm trên hóa đơn.

### 5. Hiển thị hóa đơn cũ đúng mức chứng cứ

**File:** `laravel/app/Http/Controllers/GymInvoiceController.php`, `laravel/resources/views/gym/invoice.blade.php`, màn đối soát ở `laravel/resources/views/gym/settings.blade.php` nếu cần.

1. Nếu `GIAMGIA > 0` nhưng không có bản ghi nguồn, hiện “Giảm giá theo dữ liệu hóa đơn cũ — chưa xác định nguồn” cho nhân viên/quản lý; hội viên vẫn thấy đúng số tiền đã trừ và câu giải thích ngắn gọn.
2. Nếu dòng sản phẩm không có `original_unit_price`, chỉ hiện đơn giá đã lưu. Không lấy giá gốc hiện tại để dựng khuyến mãi lịch sử.
3. Hóa đơn #144 tiếp tục nằm trong danh sách đối soát đang chặn. Số 106.000đ có thể tương ứng 5%, nhưng không gắn hạng khách nếu thiếu chứng từ gốc.
4. Không sửa hàng loạt `HOADON`, `CHITIETHOADON` hay chứng từ thanh toán cũ bằng phép tính suy đoán.
5. Các đơn gói lịch sử thuộc `membership_orders` và hóa đơn được tạo từ luồng cũ vẫn hiển thị đúng số tiền đã ghi; không gán ưu đãi hạng hiện tại cho chúng. Luồng mua gói mới qua giỏ chung mới dùng quy tắc ảnh chụp nêu trên.

### 6. Kiểm soát thay đổi mức giảm ở màn quản lý

**File:** `laravel/app/Http/Controllers/GymAdministrationController.php`, màn sửa sản phẩm/hạng khách hiện có; kiểm thử quyền và nhật ký trong nhóm feature test quản trị.

1. Khi sửa `DONGIA`, `GIAKHUYENMAI` hoặc `MUCGIAM`, ghi giá trị trước–sau, người sửa và thời điểm vào nhật ký hành động hiện có. Giữ nguyên phân quyền: nhân viên được sửa sản phẩm theo quyền; quản lý sửa hạng khách.
2. Ở form quản lý, ghi rõ giá khuyến mãi bắt đầu áp dụng cho đơn mới ngay sau khi lưu; hạng khách áp dụng cho đơn mới theo hạng của khách lúc đặt. Thêm đường dẫn dễ thấy giữa phần “Giảm giá” trên hóa đơn và các màn quản lý tương ứng cho người có quyền.
3. Kiểm thử nhân viên không được sửa hạng khách, khách không được sửa cả hai; thay đổi của người có quyền xuất hiện trong nhật ký và không sửa giá của hóa đơn đã tạo.

### 7. Báo cáo, kiểm thử và nghiệm thu

**File:** `laravel/app/Services/ManagerReportService.php`, `laravel/app/Http/Controllers/GymReportController.php`, các kiểm thử báo cáo hiện có, `laravel/docs/test-plan.md` và tài liệu nghiệp vụ liên quan.

1. Trong báo cáo xuất, ghi riêng số tiền `GIAMGIA` và nguồn ưu đãi nếu có ảnh chụp; nguồn của hóa đơn cũ thiếu dữ liệu phải là “Chưa xác định”. Các `membership_orders` lịch sử không có ảnh chụp ưu đãi không được gắn nhãn ưu đãi hạng. Doanh thu và số tiền thực thu tiếp tục lấy từ tổng phải trả/khoản thu, không trừ khoản tiết kiệm sản phẩm lần nữa.
2. Ca số học bắt buộc: sản phẩm giá gốc 1.200đ, giá bán 1.000đ, mua 2 cái, hạng giảm 10%: `TONGTIEN = 2.000đ`, `GIAMGIA = 200đ`, phải trả 1.800đ trước phí giao hàng; tiết kiệm ở giá sản phẩm là 400đ nhưng không trừ thêm. Nếu phí giao 50đ thì phải trả 1.850đ.
3. Ca hồi quy: chính sách xác minh hạng; ngày sinh trước/sau sinh nhật; đổi giá/hạng/phí giao trước và sau đặt; 0% và giá trị 0đ/100% theo chính sách đã chốt; đơn gói + sản phẩm cùng hưởng ưu đãi hạng; hóa đơn legacy không có ảnh chụp; đơn mới thiếu bản ghi nguồn; ảnh chụp có số tiền sai bị chặn; hủy/hoàn và khoản thu không bị thay đổi bởi số tiền “tiết kiệm”; xuất báo cáo; chứng từ đã ký; khách không mở được hóa đơn người khác.
4. Chạy nhóm kiểm thử liên quan, rồi toàn bộ PHPUnit, build giao diện và kiểm tra định dạng. Nghiệm thu trên bản sao database bằng ba vai trò ở giỏ, hóa đơn, chứng từ và báo cáo; so số tiền hiển thị với dữ liệu đã lưu. Sau cùng dọn bản QA.

## Điều kiện hoàn thành

- Mỗi hóa đơn **mới** có đủ dữ liệu để giải thích giá khuyến mãi sản phẩm và ưu đãi hạng khách tại thời điểm đặt.
- Giỏ, hóa đơn, chứng từ và báo cáo thống nhất tiền hàng, tiền giảm, phí giao và tiền phải trả.
- Hóa đơn **cũ** thiếu nguồn được ghi rõ là chưa xác định; các ca cần đối soát vẫn được chặn, không có khoản giảm hoặc khoản thu bị tạo thêm.
- Không có việc giảm hai lần hoặc thay đổi số tiền của hóa đơn đã đặt khi bảng giá/hạng khách đổi về sau.
- Nếu báo giá đổi trước khi khách xác nhận, hệ thống yêu cầu xem lại giá mới trước khi tạo đơn. Mỗi lần thay đổi giá khuyến mãi hoặc mức hạng có người thực hiện và giá trị trước–sau trong nhật ký.
- Một người không thể tự khai ngày sinh để tự nhận hạng học sinh/sinh viên nếu chính sách yêu cầu xác minh; tiêu chí xếp hạng hiển thị thống nhất ở đăng ký, hồ sơ, giỏ hàng và trang quản lý khách.
