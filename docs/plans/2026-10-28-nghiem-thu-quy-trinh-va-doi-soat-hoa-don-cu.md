# Nghiệm thu quy trình nội bộ và đối soát hóa đơn cũ

**Mục tiêu:** Xác nhận một tài khoản hội viên mới đi hết các bước từ đăng ký đến tập luyện, mua hàng, hủy/hoàn tiền; phân loại và xử lý có chứng cứ các hóa đơn lịch sử đang thiếu hoặc lệch dữ liệu.

**Phạm vi:** Laravel, MySQL, thanh toán tại quầy và trình duyệt trong ChatGPT. VNPay, RAG, scheduler và việc gửi email thật chưa nằm trong đợt này. Không dùng dự án C# cũ làm nguồn đối chiếu.

**Trạng thái thực hiện ngày 28/10/2026:** Đã hoàn thành luồng QA trên bản sao riêng và áp dụng migration đối soát sau khi sao lưu database ứng dụng cùng thư mục upload. Bộ kiểm thử cuối có 264 bài: 263 đạt, 1 bỏ qua. Đã tạo tài khoản hội viên mới trên clone, chạy tập thử, gói + sản phẩm, thu nhiều lần, giao hàng, hủy đơn và ghi nhận hoàn tiền mô phỏng qua UI. Đã ghi ba hóa đơn lịch sử bất thường vào màn quản lý trên ứng dụng thật, giao quản lý phụ trách và giữ chặn chờ chứng từ. Các hóa đơn đó chưa được sửa vì thiếu chứng cứ gốc. Chi tiết và giới hạn kiểm thử trong `laravel/docs/test-plan.md`.

## 1. Chuẩn bị môi trường và dữ liệu QA

1. Ghi lại commit đang kiểm tra; sao lưu CSDL ứng dụng và thư mục upload, rồi tạo hoặc làm mới **bản sao QA riêng**. Kiểm tra tên CSDL trước mọi lệnh có thể ghi; tuyệt đối không dùng `RefreshDatabase` với CSDL ứng dụng.
2. Chạy các migration còn thiếu trên bản sao QA; xác nhận schema và dữ liệu mẫu không bị mất. Chỉ áp dụng lên CSDL ứng dụng sau khi đợt QA đạt và đã có bản sao lưu.
3. Bật web dùng bản sao QA trong trình duyệt của ChatGPT. Chuẩn bị một hội viên mới, một nhân viên có quyền thử/đơn/check-in và một quản lý; ghi mã bản ghi QA để dọn đúng dữ liệu sau cùng. Không dùng tiền hoặc tài khoản ngân hàng thật.
4. Lưu mốc ban đầu: số hóa đơn, khoản thu, quyền tập, lượt check-in, tồn kho, doanh thu báo cáo và danh sách hóa đơn cần đối soát.

**Đạt khi:** Có thể phân biệt mọi thay đổi QA với dữ liệu gốc và khôi phục bản sao nếu cần.

## 2. Kiểm thử một chuỗi mới qua đủ vai trò

Thao tác bằng trình duyệt trong ChatGPT; tại mỗi bước kiểm tra **trạng thái hiển thị và dữ liệu đã lưu**, không chỉ kiểm tra trang mở được.

1. **Khách:** tạo tài khoản mới với dữ liệu duy nhất, đăng xuất, đăng nhập lại; xác nhận không thấy dữ liệu hội viên khác và không mở được URL nhân viên/quản lý.
2. **Hội viên:** gửi yêu cầu tập thử; nhìn thấy trạng thái chờ. **Nhân viên:** tiếp nhận, duyệt và đặt thời hạn. **Hội viên:** nhìn thấy quyền thử. **Nhân viên:** check-in, thử check-in lặp (phải bị từ chối), check-out; lịch sử hội viên phải có đúng một lượt.
3. **Hội viên:** tạo hóa đơn gồm gói và một sản phẩm có tồn; kiểm tra giá chụp, số lượng giữ, số tiền phải trả và hóa đơn trong lịch sử. **Nhân viên:** thu một phần, thử xác nhận sớm (phải bị từ chối), thu đủ rồi xác nhận; kiểm tra đúng một gói được cấp, đúng số lượng hàng bị trừ, và báo cáo ghi đúng tiền nhận.
4. **Nhân viên:** hoàn tất giao nhận sản phẩm. **Hội viên:** xem trạng thái giao hàng, gói có hiệu lực và lịch sử thanh toán. Thử check-in bằng quyền gói trả phí; sau check-out không còn lượt đang mở.
5. **Hội viên:** tạo hóa đơn thứ hai rồi yêu cầu hủy khi chưa trả tiền. **Nhân viên:** xác nhận hủy; kiểm tra hàng/chỗ được giải phóng, đơn giữ lịch sử và không phát sinh doanh thu.
6. **Hội viên:** tạo một đơn QA phù hợp điều kiện hoàn, nhân viên ghi nhận khoản thu. **Quản lý:** xử lý hoàn tiền theo chứng từ QA; kiểm tra tiền thu gốc còn nguyên, có chứng từ hoàn, số tiền còn phải thu/doanh thu ròng và lịch sử khách thay đổi đúng. Thử gửi lặp mã chứng từ hoặc hoàn vượt tiền (phải bị từ chối).
7. **Phân quyền:** mở trực tiếp URL và gửi thao tác ghi của vai trò khác; kiểm tra 403/404 và xác nhận dữ liệu không đổi. Với các ca hai nhân viên xử lý cùng bản ghi, dùng hai phiên độc lập nếu trình duyệt hỗ trợ; đối chiếu thêm kiểm thử tự động về khóa giao dịch và chống gửi lặp.

**Đạt khi:** Tất cả bước có trạng thái cuối rõ, người xử lý rõ, tiền/quyền tập/tồn kho chỉ thay đổi một lần; hội viên chỉ thấy dữ liệu của mình.

## 3. Kiểm kê hóa đơn cũ trước khi sửa dữ liệu

1. Lấy danh sách từ `GymPortalService::unreconciledInvoices()` và màn **Quản lý → Cài đặt → Dữ liệu cần đối chiếu**. Xuất bảng nội bộ gồm mã hóa đơn, khách, trạng thái, ngày, tổng tiền, giảm giá, phí giao hàng, thành tiền, các dòng chi tiết, khoản thu/hoàn và đơn gói liên kết.
2. Kiểm tra riêng từng nguồn `CHITIETHOADON` và `gym_invoice_lines`; không để phép `COALESCE` che việc hai nguồn cùng tồn tại nhưng khác nhau. Đối chiếu tổng `số lượng × đơn giá` với `TONGTIEN`, rồi đối chiếu `THANHTIEN = TONGTIEN − GIAMGIA + phí giao hàng`.
3. Phân loại từng mã: **thiếu chi tiết**, **lệch tổng**, **khoản thu/trạng thái không khớp**, **trùng hoặc sai liên kết đơn gói**, **dịch vụ cũ đã ngừng hỗ trợ**, hoặc **hợp lệ**. Giữ mã hóa đơn và bằng chứng nguồn cho từng kết luận.
4. Với hóa đơn đã thu tiền, ưu tiên đối chiếu chứng từ thu/hoàn và quyền đã cấp trước khi cân nhắc sửa tổng tiền. Không dùng giá sản phẩm hiện tại để suy ra giá lịch sử.

**Đạt khi:** Mỗi hóa đơn bất thường có đúng một nhóm lỗi chính, mức ảnh hưởng tiền/quyền/tồn được ghi rõ và có hướng xử lý cụ thể.

## 4. Xử lý và bảo vệ dữ liệu lịch sử

1. Chỉ sửa bản ghi có chứng cứ đủ mạnh từ hóa đơn, dòng hàng, khoản thu và nhật ký hiện có. Chụp trạng thái trước/sau; sửa trong transaction; ghi người thao tác, lý do và mã chứng từ vào nhật ký đối soát.
2. Hóa đơn thiếu chứng cứ hoặc chứa dịch vụ cũ đã ngừng được giữ **chỉ đọc/chặn thu và chốt**; gắn trạng thái cần quản lý đối chiếu cùng người chịu trách nhiệm. Không tạo chi tiết giả, không tự ghi tiền đã nhận, không tự cấp gói hay trừ tồn.
3. Nếu màn hiện tại chưa cho quản lý ghi nhận kết quả đối soát, bổ sung thao tác quản lý tối thiểu và kiểm thử quyền truy cập. Nếu dữ liệu hữu hạn có thể sửa bằng một lần chuyển đổi được kiểm toán, ưu tiên chuyển đổi một lần thay vì mở thêm màn CRUD thường trực.
4. Chạy lại danh sách bất thường, chi tiết hóa đơn và báo cáo trước/sau. Chênh lệch doanh thu phải có giải thích theo từng mã; hóa đơn chưa giải quyết vẫn phải nằm trong danh sách chờ, không bị ẩn khỏi báo cáo.

**Đạt khi:** Không còn hóa đơn bất thường bị bỏ quên; mỗi mã được sửa có bằng chứng và nhật ký, mỗi mã chưa sửa có trạng thái, lý do và người chịu trách nhiệm.

## 5. Hồi quy và bàn giao kết quả

1. Nếu phát hiện lỗi code, viết kiểm thử tái hiện rồi sửa đúng service/controller liên quan; ưu tiên `GymCommerceService`, `GymPortalService`, `GymInvoiceController`, `GymRefundService` và các bài `GymCommerceTest`, `ManualRefundWorkflowTest`, `TrialRegistrationWorkflowTest`, `UnifiedInvoiceBrowseTest`, `AuthorizationPolicyTest`.
2. Chạy các bài liên quan sau mỗi sửa, sau đó chạy **một lần** toàn bộ PHPUnit, build giao diện và kiểm tra định dạng. Bài bỏ qua về máy chủ MySQL cấp tài khoản riêng được ghi tách biệt.
3. Kiểm tra lại trên trình duyệt đúng những màn đã sửa, ở kích thước desktop và điện thoại; lưu bằng chứng theo vai trò, mã bản ghi và kết quả mong đợi/thực tế.
4. Xóa đúng bản ghi QA trên bản sao thử hoặc bỏ bản sao; kiểm tra CSDL ứng dụng và thư mục upload gốc không bị thay đổi bởi QA. Cập nhật `laravel/docs/test-plan.md` và tài liệu nghiệp vụ bằng kết quả thật.

**Điều kiện kết luận khép kín trong phạm vi này:** Chuỗi tài khoản mới → thử → tập → mua → thu/chốt → giao hàng → hủy/hoàn chạy trọn; không có khoản tiền, quyền tập hoặc tồn kho bị ghi hai lần; mọi hóa đơn cũ bất thường đã được giải quyết hoặc được chặn rõ với người chịu trách nhiệm. Các tích hợp ngoài phạm vi được ghi riêng, không dùng để đánh giá đợt này.
