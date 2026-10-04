# Nghiệp vụ khách hàng và gói hội viên

**Phạm vi đồ án môn học:** Một phòng tập. Khách tạo tài khoản, mua gói, chọn ngày bắt đầu, xem thời hạn và lịch sử tập; nhân viên xác nhận tiền và check-in. Thanh toán là tiền mặt hoặc chuyển khoản được **nhân viên kiểm tra thủ công**. Không xây chi nhánh, cổng thanh toán, xác minh tài khoản nhiều bước, thông báo tự động hoặc hoàn tiền trên web.

## 1. Người tham gia và khái niệm

| Vai trò/khái niệm | Ý nghĩa |
| --- | --- |
| Người xem web | Xem gói và giá; có thể gửi biểu mẫu tập thử. Biểu mẫu này không tự tạo tài khoản hay quyền vào tập. |
| Khách có tài khoản | Đăng nhập, xem/sửa hồ sơ được phép, đặt mua gói và xem đơn, gói, lịch sử của **chính mình**. Có tài khoản chưa đồng nghĩa được vào tập. |
| Hội viên còn hạn | Khách có gói đã được nhân viên xác nhận thanh toán và đang trong thời hạn; được nhân viên check-in. |
| Nhân viên | Kiểm tra đã nhận tiền, xác nhận đơn, tra cứu quyền tập và ghi giờ vào/ra. |
| Quản lý | Xem gói đang bán, đăng ký và doanh thu từ đơn đã thanh toán. |

Khách hết hạn vẫn giữ tài khoản và lịch sử để gia hạn hoặc quay lại sau này.

## 2. Quy tắc tài khoản và hồ sơ

1. Khách có thể xem gói trước khi đăng nhập. Bấm `Tham gia` sẽ đưa khách chưa đăng nhập tới trang đăng nhập/đăng ký, rồi trở lại gói đã chọn.
2. Khách đăng ký bằng họ tên, số điện thoại, email, tên đăng nhập và mật khẩu theo biểu mẫu hiện có. Hệ thống kiểm tra trường bắt buộc, định dạng và trùng tên đăng nhập/email/số điện thoại. Đăng ký thành công có thể đăng nhập ngay; **không thêm bước xác minh email hoặc điện thoại**.
3. Khách đăng nhập bằng tên đăng nhập và mật khẩu. Chức năng quên mật khẩu qua email/OTP hiện có có thể giữ nguyên; OTP đó chỉ dùng để khôi phục, không bắt buộc khi đăng ký hoặc mua gói.
4. Ở “Hồ sơ của tôi”, khách xem mã khách, họ tên, email, điện thoại và thông tin cá nhân đã lưu. Khách tự sửa họ tên, ngày sinh, giới tính và điện thoại; trong phạm vi đồ án không tự đổi tên đăng nhập/email. Nhân viên hỗ trợ sửa email nếu cần và kiểm tra trùng trước khi lưu.
5. `MAKH` là mã khách cố định để liên kết đơn, gói và check-in. Khách chỉ xem/sửa dữ liệu của mình; không được tự đánh dấu đơn là `Đã thanh toán` hoặc tạo/sửa check-in. Mật khẩu không được hiển thị trên trang hồ sơ.

## 3. Quy tắc gói tập và thời hạn

1. Gói có tên, mô tả, **giá toàn kỳ** và thời hạn là số tháng nguyên dương. Hệ thống lấy giá khi tạo đơn, không tin giá do trình duyệt gửi lên. Giá của đơn đã tạo không đổi khi giá gói được sửa sau đó.
2. Khách chọn **ngày bắt đầu mong muốn**, không trước ngày đặt mua. Đây là ngày dự kiến cho tới khi nhân viên xác nhận đã nhận tiền. Đơn chưa thanh toán không cấp quyền tập.
3. Nếu ngày đã chọn qua mất khi nhân viên xác nhận tiền, nhân viên yêu cầu khách chọn lại ngày từ ngày xác nhận trở đi trước khi hoàn tất đơn. Những ngày khách chưa có quyền tập không bị trừ vào thời hạn.
4. Một khách không có hai kỳ **đã thanh toán** chồng ngày. Hệ thống gợi ý bắt đầu vào **ngày hết hạn** của kỳ đang dùng hoặc sau kỳ tương lai đã mua. Khách có thể chọn ngày khác nếu **toàn bộ khoảng sử dụng của gói mới** không chồng bất kỳ kỳ đã thanh toán nào; nhờ vậy vẫn có thể mua gói vừa với một khoảng nghỉ còn trống. Mua kỳ mới không sửa hoặc xóa kỳ cũ.
5. Kỳ có hiệu lực từ **00:00 ngày bắt đầu** đến **trước 00:00 ngày hết hạn** theo giờ Việt Nam. Trong dữ liệu, `NGAYKETTHUC` được hiểu là mốc **không còn quyền tập**; chỉ cho check-in khi thời điểm vào `>= NGAYBATDAU` và `< NGAYKETTHUC`. Giao diện hiển thị thêm **ngày tập cuối** để khách không hiểu nhầm mốc hết hạn.
6. Thời hạn tính theo **tháng lịch**: ngày hết hạn là ngày cùng số sau số tháng đã mua. Nếu tháng đích không có ngày đó, ngày hết hạn là ngày 1 của tháng kế tiếp. Ví dụ, bắt đầu 02/10/2026 với gói 1 tháng thì tập hết 01/11/2026; bắt đầu 31/01/2026 thì tập hết 28/02/2026. Cách tính này phải hiện trước khi xác nhận mua.

## 4. Luồng từ đặt mua đến gia hạn

1. Khách đăng nhập, chọn gói và ngày bắt đầu. Hệ thống hiển thị giá toàn kỳ, ngày tập cuối dự kiến; không cho đặt mua nếu toàn bộ kỳ dự kiến chồng một kỳ đã thanh toán.
2. Khách đặt mua. Hệ thống tạo một đơn `Chờ thanh toán` với mã đơn, giá gói tại lúc đặt và ngày dự kiến. Nếu đổi ý, khách yêu cầu nhân viên hủy đơn; nhân viên kiểm tra chưa nhận tiền trước khi hủy.
3. Khách trả tiền mặt tại quầy hoặc chuyển khoản theo hướng dẫn. Chọn phương thức hoặc bấm “đã chuyển” **không** làm đơn thành đã thanh toán. Nếu số tiền thực nhận chưa đủ, đơn tiếp tục `Chờ thanh toán` và nhân viên báo số tiền còn thiếu.
4. Nhân viên kiểm tra đã nhận đủ tiền. Hệ thống tính lại **cả khoảng sử dụng**; nếu ngày dự kiến đã qua hoặc khoảng đó chồng một kỳ khác vừa mua, nhân viên cùng khách chốt ngày hợp lệ. Sau đó nhân viên xác nhận đơn `Đã thanh toán`; hệ thống tạo **đúng một** kỳ đăng ký gói liên kết với đơn. Nếu không tạo được kỳ đăng ký thì đơn không được để ở trạng thái đã thanh toán trong hệ thống. Bấm xác nhận lại không tạo thêm kỳ.
5. Khách xem “Đơn của tôi” và “Gói của tôi”: đơn còn chờ, kỳ đã trả tiền nhưng chưa bắt đầu, kỳ đang dùng và kỳ đã hết hạn. Kỳ tương lai hiển thị `Chờ hiệu lực`.
6. Khi khách đến tập, nhân viên tra cứu bằng mã khách hoặc số điện thoại, đối chiếu tên rồi kiểm tra có kỳ đã thanh toán, đang hiệu lực và chưa có lượt vào còn mở. Nếu hợp lệ thì ghi giờ vào; khi khách ra ghi giờ ra. Nếu không hợp lệ thì hiển thị lý do. Khách xem lịch sử vào/ra trong tài khoản, không tự sửa được.
7. Khách gia hạn bằng cách đặt một kỳ mới trên cùng tài khoản. Hệ thống gợi ý ngày nối tiếp kỳ cũ; khách có thể chọn ngày khác nếu không chồng kỳ đã mua. Những ngày nằm giữa hai kỳ không có quyền vào tập. Hết hạn không xóa tài khoản, đơn hay lịch sử tập.

## 5. Trạng thái và trường hợp cần xử lý

| Đối tượng | Trạng thái/quy tắc |
| --- | --- |
| Đơn mua gói | `Chờ thanh toán` → `Đã thanh toán` hoặc `Đã hủy`. Chỉ nhân viên hủy sau khi kiểm tra chưa nhận tiền. Nếu tiền tới sau khi đã hủy, nhân viên đối soát và hướng khách lập đơn mới; không tự kích hoạt đơn cũ. Đơn không tự hết hạn trong phạm vi đồ án. |
| Kỳ hội viên | `Chờ hiệu lực`, `Đang hiệu lực`, `Hết hạn`, dựa trên đơn đã thanh toán và ngày bắt đầu/hết hạn. |
| Check-in | Chưa trả tiền, chưa tới ngày bắt đầu, đã hết hạn hoặc còn lượt vào chưa ghi ra thì không cho vào lần mới. Nhân viên vẫn ghi giờ ra cho lượt đang mở nếu gói vừa hết hạn. Nếu khách quên check-out, nhân viên đóng lượt đang mở sau khi xác minh, ghi giờ ra và lý do điều chỉnh; sau đó khách mới check-in lần tiếp theo. |
| Tập thử | Gửi biểu mẫu chỉ tạo yêu cầu tập thử, không cấp quyền hội viên. |
| Hoàn tiền | Ngoài phạm vi đồ án. Quản lý xử lý trường hợp thực tế ngoài hệ thống; không sửa/xóa lịch sử để giả lập hoàn tiền. |

## 6. Màn hình tối thiểu

| Người dùng | Màn hình cần có |
| --- | --- |
| Khách | Đăng ký/đăng nhập; hồ sơ; danh sách/chi tiết gói; đặt mua; “Đơn của tôi”; “Gói của tôi”; lịch sử tập. |
| Nhân viên | Danh sách đơn chờ để xác nhận đã nhận tiền; tra cứu khách; check-in/check-out. |
| Quản lý | Danh sách gói và đăng ký; tổng tiền từ các đơn đã xác nhận thanh toán. |

Khách xem trạng thái trực tiếp trên web; đồ án không cần email nhắc hết hạn hoặc trang yêu cầu hoàn tiền.

## 7. Đối chiếu với mã nguồn hiện tại

- `AccountController` đã có đăng ký, đăng nhập, đăng xuất và khôi phục mật khẩu; trang chủ đã có biểu mẫu tập thử. `KHACHHANG` liên kết với gói, hóa đơn và check-in. Chưa có trang khách tự xem/sửa hồ sơ, “Đơn của tôi”, “Gói của tôi” hoặc lịch sử tập; menu đang có link `Account/Profile` nhưng controller chưa có action đó.
- `GOITAP` đã có tên, giá, `THOIHAN`, mô tả; cần thống nhất `THOIHAN` là tháng. `DANGKYGOITAP` đã có khách, gói và ngày bắt đầu/kết thúc; khi triển khai phải lưu `NGAYKETTHUC` theo quy tắc mốc hết hạn ở mục 3. `HOADON`, `CHITIETHOADON.MADKGT` và `CHECKIN` có dữ liệu nền cho đơn, gói và lượt vào/ra. `HOADON` hiện chưa có trường riêng cho phương thức, thời điểm và nhân viên xác nhận tiền nếu muốn hiển thị lại các thông tin này.
- Trang nhân viên lấy khách qua `GetHocVien()`, hiện nối tới đăng ký PT nên không bao phủ toàn bộ khách. Quy tắc chống trùng trong stored procedure Oracle chưa thể xác nhận vì mã procedure không có trong repository.
- Nút `Tham gia` hiện chưa dẫn tới luồng mua. `CartRepository.TaoHoaDon` chỉ tạo chi tiết cho hàng hóa và đánh dấu `BANK` là `Đã thanh toán` khi khách chọn phương thức; không được dùng nguyên quy tắc này để kích hoạt gói tập.
- Trang chủ đang quảng bá gói “một chi nhánh/tất cả chi nhánh”; khi triển khai phạm vi một phòng tập cần sửa dòng giới thiệu đó cho đúng.

## 8. Điều kiện nghiệm thu

1. Gửi biểu mẫu tập thử không tự tạo tài khoản hay quyền check-in. Khách đăng ký hợp lệ có thể đăng nhập ngay, không qua xác minh email; thông tin định danh trùng bị từ chối.
2. Khách đã đăng nhập nhưng chưa mua gói thấy “Chưa có gói tập” và không được check-in. Khách chỉ xem hồ sơ, đơn và lịch sử của mình.
3. Đặt gói, chọn chuyển khoản nhưng nhân viên chưa xác nhận: đơn vẫn `Chờ thanh toán`, chưa có quyền tập; khách không thể tự chuyển trạng thái đơn.
4. Nhân viên xác nhận một đơn đã nhận tiền: có đúng một kỳ hội viên liên kết với đơn. Xác nhận lặp không tạo thêm kỳ hoặc cộng thêm thời hạn.
5. Khách chọn bắt đầu 10/10 nhưng tiền được xác nhận 12/10: nhân viên yêu cầu chốt ngày từ 12/10 trở đi; kỳ mới vẫn có đủ số tháng đã mua.
6. Gói tập hết ngày 01/11: kỳ gia hạn bắt đầu sớm nhất 02/11. Nếu khách chọn 05/11 thì từ 02–04/11 không được check-in.
7. Gói tương lai chưa được check-in; gói đang hiệu lực được ghi giờ vào/ra; gói hết hạn bị từ chối lượt vào mới. Khách hết hạn vẫn dùng được tài khoản để xem lịch sử và mua gói tiếp.
8. Gói 1 tháng bắt đầu 31/01/2026 được tập hết 28/02/2026 và bị từ chối lượt vào từ 00:00 ngày 01/03/2026.
9. Khách chuyển thiếu tiền: nhân viên không xác nhận đơn, khách chưa được tập. Yêu cầu hủy đơn khi đã có tiền đến phải được nhân viên kiểm tra và giải quyết, không tự hủy để mất dấu khoản tiền.
10. Khách quên check-out: lần check-in mới bị chặn cho tới khi nhân viên đóng lượt cũ, có ghi lý do; lịch sử vẫn xem được.
11. Khách đã mua kỳ bắt đầu 01/12; đơn gói 1 tháng mới dự kiến bắt đầu 15/11 bị từ chối vì **khoảng sử dụng** của gói mới chồng kỳ đã mua, dù riêng ngày bắt đầu 15/11 chưa chồng.
