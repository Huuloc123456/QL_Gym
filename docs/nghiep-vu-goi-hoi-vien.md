# Nghiệp vụ hiện tại của The Gym

Tài liệu này mô tả ứng dụng Laravel đang chạy trong repository. Phạm vi là một phòng tập. Website hiện không mở luồng đặt PT; các bảng và chứng từ PT cũ được giữ để tra cứu lịch sử. Các chức năng được mô tả theo dữ liệu trong MySQL và quyền của từng vai trò.

## Vai trò

| Vai trò | Nghiệp vụ chính |
| --- | --- |
| Khách chưa đăng nhập | Xem gói, sản phẩm, lớp học và đăng ký tài khoản hội viên. |
| Hội viên | Quản lý hồ sơ được phép, gửi yêu cầu tập thử, đặt gói/mua hàng/đăng ký lớp qua giỏ hàng, xem hóa đơn và lịch sử của mình, gửi yêu cầu hủy khi đơn còn chờ. |
| Nhân viên | Tiếp nhận yêu cầu tập thử; xử lý hóa đơn và đơn gói; tạo/sửa sản phẩm, cập nhật ảnh/tồn kho; xác nhận khoản thu; check-in/check-out hội viên. |
| Quản lý | Xem báo cáo; quản lý hội viên, gói, sản phẩm, nhân viên và quyền; cập nhật thông tin phòng tập, banner và tài liệu chatbot; phân công, ghi bằng chứng và theo dõi đối soát hóa đơn cũ. |

Quyền được kiểm tra ở server theo vai trò. Nhân viên không vào được trang quản lý; hội viên chỉ xem dữ liệu thuộc tài khoản của mình. Nhân viên được tạo/sửa sản phẩm và điều chỉnh tồn, còn xóa sản phẩm dành cho quản lý.

## Đăng ký và tài khoản hội viên

1. Khách đăng ký bằng thông tin liên hệ, tên đăng nhập và mật khẩu hợp lệ. Hệ thống từ chối dữ liệu định danh trùng và đăng nhập hội viên ngay sau đăng ký; tài khoản khách không cần xác minh email bổ sung.
2. Hội viên có thể cập nhật họ tên, số điện thoại, ngày sinh và giới tính. Tên đăng nhập và email được bảo vệ theo quy tắc tài khoản.
3. Hội viên đăng ký tập thử bằng tài khoản của chính mình. Nhân viên tiếp nhận/đánh dấu đã liên hệ; quản lý đối chiếu yêu cầu cũ với tài khoản đang hoạt động bằng tên đăng nhập và số điện thoại khớp chính xác sau chuẩn hóa. Yêu cầu chưa được duyệt không tạo quyền vào tập.
4. Hội viên xem các gói đã đặt, trạng thái hóa đơn, gói hiện tại/tương lai/đã hết hạn và lịch sử vào tập của chính mình.
5. Quên mật khẩu dùng liên kết đặt lại một lần qua mailer đã cấu hình. Môi trường local mặc định ghi thư vào log, không gửi email ra ngoài.

## Gói hội viên và check-in

1. Khách chọn gói đang mở bán, sản phẩm hoặc lớp học, đưa vào giỏ và xác nhận một hóa đơn. Giá, khuyến mãi, tồn, lịch và ngày bắt đầu được kiểm tra lại từ MySQL khi đặt. Đơn gói mới không tạo thêm bản ghi đơn gói chờ riêng.
2. Đơn chưa thu đủ tiền ở trạng thái chờ, chưa cấp quyền tập. Hội viên có thể gửi yêu cầu hủy; sau yêu cầu, hệ thống khóa thanh toán. Nhân viên trao đổi với khách rồi hủy hoặc xác nhận tiếp tục trước khi thu/chốt.
3. Nhân viên ghi nhận tiền mặt/chuyển khoản sau khi đối chiếu thực tế. Khi đã thu đủ và xác nhận hợp lệ, hệ thống mới cấp dịch vụ, trừ tồn hoặc đăng ký lớp; thao tác lặp không tạo giao dịch thứ hai.
4. VNPay chỉ xác nhận khi IPN hợp lệ kiểm tra đúng chữ ký, mã đơn, số tiền và trạng thái. Trang quay về của khách không tự xác nhận giao dịch. IPN lặp không tạo thêm gói.
5. Quản lý ghi nhận hoàn tiền sau khi đã hoàn bên ngoài website; chứng từ liên kết với khoản thu gốc, có mã chứng từ/lý do/người thao tác. Báo cáo tách tiền nhận, tiền hoàn và thu ròng.
6. Thời hạn dùng tháng lịch theo múi giờ Việt Nam; ngày hết hạn là thời điểm bắt đầu ngày khách không còn quyền tập. Các kỳ đã thanh toán không được chồng lấn.
7. Nhân viên tra cứu hội viên, kiểm tra quyền gói trả phí hoặc tập thử còn hạn, rồi ghi giờ vào/ra. Không cho check-in nếu quyền chưa hiệu lực/hết hạn hoặc lượt trước chưa đóng. Hội viên chỉ xem lịch sử, không tự sửa lượt tập.

## Sản phẩm và hóa đơn

1. Khách duyệt danh mục, xem ảnh/giá/tình trạng hàng, thêm sản phẩm vào giỏ, nhập thông tin nhận hàng và đặt đơn.
2. Đơn chờ giữ số lượng hàng để tránh bán vượt tồn. Khi hóa đơn được xác nhận đã thu đủ, hàng được trừ tồn; khi hủy đơn hợp lệ, phần đang giữ được giải phóng. Nhân viên không thể giảm tồn thấp hơn lượng đang giữ.
3. Hóa đơn cửa hàng, lớp học và đơn gói được tra cứu trong các màn phù hợp. Danh sách hóa đơn hỗ trợ tìm kiếm/lọc/phân trang để tra cứu lịch sử.
4. Chuyển khoản thủ công có thể hiển thị mã VietQR theo số tiền và nội dung của hóa đơn. QR chỉ giúp điền thông tin chuyển khoản; ngân hàng không tự xác nhận đơn. Nhân viên phải đối chiếu khoản tiền thực nhận.
5. Hủy hóa đơn chưa thu tiền giữ lại lịch sử trạng thái, không xóa giao dịch; nếu đơn có giữ hàng, hệ thống trả số lượng về tồn có thể bán. Hoàn tiền tự động không thuộc phạm vi.
6. Quản lý xem riêng hai nguồn chi tiết hóa đơn, khoản thu/hoàn và liên kết gói trong **Dữ liệu cần đối chiếu**. Mỗi sai lệch cần nhóm lỗi, bằng chứng, hướng xử lý và quản lý phụ trách. Hóa đơn đang sai dữ liệu bị chặn thu/chốt; chỉ được đánh dấu đã xử lý sau khi nguồn hóa đơn đã hết sai lệch. Không tự tạo dòng hàng, chứng từ thu, giá lịch sử hay quyền tập.
7. Giá khuyến mãi chỉ có hiệu lực khi lớn hơn 0 và thấp hơn giá gốc; dữ liệu khuyến mãi cũ bằng 0 hoặc cao hơn giá gốc được hiểu là không giảm. Danh mục, tìm/lọc, chatbot, giỏ và hóa đơn dùng cùng cách hiểu này.
8. Ưu đãi hạng được tính trên tổng giá bán sau khuyến mãi của sản phẩm, gói và lớp; không giảm phí giao hàng. Giá khuyến mãi sản phẩm đã nằm trong đơn giá nên không bị trừ lần hai. Hạng “Học sinh, Sinh viên” cần quản lý xác minh trực tiếp trước khi giảm; ngày sinh không tự cấp ưu đãi. Khách mới mặc định vào hạng “Gym Null”. Khách hiện đang mang hạng học sinh/sinh viên chưa được giảm trên đơn mới cho đến khi được xác minh; đơn cũ không bị sửa.
9. Khi mở giỏ, hệ thống lưu báo giá theo phiên và đối chiếu lại giá hàng, số lượng, hạng/ưu đãi và phí giao lúc đặt. Nếu có thay đổi, đơn chưa được tạo và khách phải xem lại giỏ. Mỗi hóa đơn mới lưu giá gốc/giá bán cùng căn cứ ưu đãi; hóa đơn cũ thiếu nguồn hiển thị “chưa xác định” và không suy diễn từ phần trăm.
10. Quản lý có thể xác minh/thu hồi xác minh hạng với lý do; thao tác được ghi nhật ký. Sửa giá gốc, giá khuyến mãi hoặc mức ưu đãi ghi lại giá trị trước/sau. Báo cáo Excel tách số tiền giảm và nguồn giảm; đơn cũ không có ảnh chụp ghi “Chưa xác định”.

## Nhân viên và quản lý

- Nhân viên dùng khu vực vận hành để xử lý yêu cầu tập thử, đơn gói, hóa đơn cửa hàng/lớp học, sản phẩm và check-in. Màn sản phẩm không cấp quyền xóa.
- Quản lý tra cứu hồ sơ hội viên kèm gói, hóa đơn và check-in; quản lý giá/gói, sản phẩm, tài khoản nhân viên và quyền, nội dung trang chủ, banner cùng cài đặt phòng tập.
- Báo cáo theo khoảng ngày phân biệt đơn được tạo, doanh thu đã xác nhận và các khoản thu thực tế theo phương thức. Tệp Excel gồm tổng quan, đơn hàng và khoản thu.
- Khi nhiều nhân viên mở cùng một hóa đơn, server phải kiểm tra trạng thái mới nhất trước thao tác thu tiền, chốt hoặc hủy để tránh ghi nhận hai lần hoặc xử lý một hóa đơn đã đổi trạng thái.
- Ba hóa đơn lịch sử `#138`, `#144`, `#182` đang bị chặn chờ chứng từ và giao cho quản lý The Gym. Cần có hóa đơn/chứng từ gốc mới quyết định sửa hoặc đóng các hồ sơ này; dữ liệu hiện có chưa đủ để xác nhận tiền đã nhận.

## Chatbot và tài liệu RAG

- Quản lý thêm/sửa/tạm ngừng/xóa nội dung và nhập câu hỏi từ Excel qua màn quản lý. Laravel/MySQL giữ tài liệu gốc; dịch vụ RAG lập chỉ mục các tài liệu đang bật.
- Chatbot sử dụng kiến thức phòng tập và dữ liệu công khai liên quan, có thể trả lời một số phép tính đơn giản khi đủ thông tin; giá tham khảo phải được nói rõ. Câu hỏi ngoài phạm vi bị từ chối. Chatbot không được yêu cầu hoặc tiết lộ mật khẩu, OTP, dữ liệu tài khoản/hóa đơn riêng.
- Trên ô chat, Enter gửi câu hỏi và Shift+Enter xuống dòng. Người dùng có thể mở trợ lý từ nút hỏi đáp trên website.

## Phạm vi chưa bao gồm

Nhiều chi nhánh, luồng đặt PT mới, tự động hoàn tiền, xác minh tài khoản nhiều bước và tự động xác nhận chuyển khoản ngân hàng nằm ngoài phạm vi hiện tại. VNPay sandbox cần cấu hình khóa và IPN HTTPS công khai; VietQR chuyển khoản thủ công không thay thế bước đối soát. Trạng thái kiểm thử thực tế và các mục chưa xác minh được ghi ở [kế hoạch kiểm thử](../laravel/docs/test-plan.md).
