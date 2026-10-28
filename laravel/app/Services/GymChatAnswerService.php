<?php

namespace App\Services;

use App\Models\GymPackage;
use App\Models\GymSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GymChatAnswerService
{
    public function __construct(private GymCommerceService $shop) {}

    /** @return array{answer: string, sources: list<string>}|null */
    public function answer(string $question): ?array
    {
        $privateAnswer = $this->answerPrivateAccountQuestion($question);
        if ($privateAnswer !== null) {
            return $privateAnswer;
        }

        $text = $this->normalize($question);
        $settings = GymSetting::query()->find(1);
        if (preg_match('/\b(quen mat khau|doi mat khau|dat lai mat khau)\b/', $text)) {
            return $this->result('Quên mật khẩu: mở /quen-mat-khau và nhập email tài khoản. Đổi mật khẩu: đăng nhập rồi mở Bảo mật tài khoản, nhập mật khẩu hiện tại và xác nhận mật khẩu mới. Email thật chỉ đến hộp thư khi máy chủ đã cấu hình gửi thư.', 'Hướng dẫn tài khoản');
        }
        foreach ($settings?->faq_items ?? [] as $faq) {
            $title = $this->normalize($faq['question'] ?? '');
            if (mb_strlen($title) >= 8 && ($text === $title || str_contains($text, $title))) {
                return $this->result(Str::limit(strip_tags($faq['answer'] ?? ''), 1600), 'Câu hỏi thường gặp do quản lý cập nhật');
            }
        }
        if (preg_match('/\b(gio mo cua|mo cua may gio|may gio mo cua|dia chi|o dau|so dien thoai|lien he|email)\b/', $text)) {
            $details = array_filter([
                $settings?->opening_hours ? 'Giờ mở cửa: '.$settings->opening_hours : null,
                $settings?->address ? 'Địa chỉ: '.$settings->address : null,
                $settings?->phone ? 'Điện thoại: '.$settings->phone : null,
                $settings?->email ? 'Email: '.$settings->email : null,
            ]);

            return $details ? $this->result(implode("\n", $details), 'Cấu hình công khai của phòng tập') : null;
        }
        if (preg_match('/\b(giao hang|phi giao|nhan tai quay|van chuyen)\b/', $text)) {
            return $this->result('Nhận sản phẩm tại quầy không tính phí giao. Giao theo địa chỉ cần địa chỉ đã lưu, người nhận và điện thoại. Phí giao hiện tại: '.$this->money((int) ($settings?->shipping_fee ?? 0)).'. Ưu đãi hạng khách không giảm phí giao; phí cuối cùng được hiển thị trước khi đặt đơn.', 'Cấu hình giao hàng và giỏ hàng');
        }
        if (preg_match('/\b(thanh toan|chuyen khoan|nhan tien|huy don|doi soat)\b/', $text)) {
            return $this->result('Chọn tiền mặt hoặc chuyển khoản không tự kích hoạt dịch vụ. Nhân viên ghi nhận khoản thu và xác nhận khi đủ tiền; VNPay chỉ chốt sau thông báo IPN hợp lệ. Đơn chưa nhận tiền có hạn thanh toán '.$settings?->payment_hours.' giờ. Đơn đã nhận tiền hoặc đang đối soát không được tự hủy. Hãy dùng mã hóa đơn khi liên hệ quầy.', 'Quy trình hóa đơn và thanh toán');
        }
        if (preg_match('/\b(goi|membership)\b/', $text)) {
            if (preg_match('/\b(thoi han|ngay tap cuoi|tinh the nao|tinh the nao|het han)\b/', $text)) {
                return $this->result('Gói trả phí tính theo số tháng đã đặt, từ ngày bắt đầu được xác nhận. Ngày tập cuối được ghi trên gói; ngày kế tiếp hết quyền. Ví dụ gói 1 tháng bắt đầu 31/01/2027 có ngày tập cuối 28/02/2027. Đổi giá hoặc thời hạn gói sau khi đặt không đổi thời hạn đã chụp trên đơn.', 'Quy tắc thời hạn gói tập');
            }
            $packages = GymPackage::query()->where('is_active', true)->orderBy('duration_months')->limit(8)->get();

            return $this->result($packages->isEmpty() ? 'Chưa có gói tập đang mở bán.' : "Các gói đang mở bán:\n".$packages->map(fn ($package) => '- '.$package->name.': '.$this->money($package->price).' / '.$package->duration_months.' tháng')->join("\n")."\nGiá trước ưu đãi hạng khách; số tiền cuối cùng hiển thị tại giỏ.", 'Danh mục gói tập hiện tại');
        }
        if (preg_match('/\b(lop hoc|lop tap|yoga|zumba)\b/', $text)) {
            $classes = DB::table('LOPHOC')->whereDate('NGAYBATDAU', '>=', now()->toDateString())->orderBy('NGAYBATDAU')->limit(5)->get();

            return $this->result($classes->isEmpty() ? 'Chưa có lớp còn trong thời gian nhận đăng ký. Theo dõi trang Lớp học để xem lịch mới.' : $classes->map(fn ($class) => '- '.$class->TENLOP.': '.$this->money((int) $class->HOCPHI).', bắt đầu '.substr($class->NGAYBATDAU, 0, 10).', '.$class->SOBUOI.' buổi.')->join("\n")."\nChỗ còn được kiểm tra lại khi đặt đơn.", 'Danh mục lớp học SQL');
        }
        if (preg_match('/\b(pt|huan luyen vien)\b/', $text)) {
            $trainers = DB::table('NHANVIEN as n')->join('CHUCVU as c', 'c.MACHUCVU', '=', 'n.MACHUCVU')->join('gym_pt_rates as r', 'r.employee_id', '=', 'n.MANV')
                ->where('c.TENCHUCVU', 'PT')->where('r.price', '>', 0)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('NHANVIEN_CHUYENMON as s')->whereColumn('s.MANV', 'n.MANV'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users as u')->whereColumn('u.sql_employee_id', 'n.MANV')->where('u.is_active', false))
                ->select('n.TENNV', 'r.price')->limit(5)->get();

            return $this->result($trainers->isEmpty() ? 'Chưa có PT đủ cấu hình nhận đăng ký trực tuyến. Vui lòng liên hệ quầy.' : $trainers->map(fn ($pt) => '- '.$pt->TENNV.': '.$this->money((int) $pt->price).' mỗi buổi.')->join("\n")."\nChọn số buổi và thanh toán trước khi xếp lịch.", 'PT, chuyên môn và giá buổi hiện tại');
        }
        if (preg_match('/\b(tap thu|trai nghiem)\b/', $text)) {
            return $this->result('Gửi form Trải nghiệm trên trang chủ để nhân viên liên hệ và xác minh. Gửi form chưa tạo quyền vào tập. Nhân viên duyệt thời lượng thử tối đa 7 ngày; mỗi số điện thoại chỉ được duyệt một lần. Thời lượng tập thử tính theo ngày, khác với gói trả phí tính theo tháng.', 'Quy trình duyệt tập thử');
        }
        if (preg_match('/\b(check in|check-in|check out|check-out|vao tap|lich su tap)\b/', $text)) {
            return $this->result('Đưa mã khách hàng hoặc số điện thoại cho nhân viên. Chỉ vào tập khi có gói đang hiệu lực hoặc quyền tập thử đã duyệt. Không thể check-in lần hai nếu lượt trước chưa check-out. Lịch sử vào/ra nằm trong tài khoản của bạn.', 'Quyền tập và lịch sử check-in');
        }
        if (preg_match('/\b(san pham|whey|protein|creatine|binh nuoc|ao tap|phu kien|dung cu|cua hang)\b/', $text)) {
            $tokens = array_values(array_diff(preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY), ['toi', 'ban', 'co', 'nhung', 'cac', 'danh', 'sach', 'ho', 'tro', 'san', 'pham', 'gi', 'gym', 'nao', 'mua', 'con', 'hang', 'gia', 'bao', 'nhieu', 'the', 'cho', 'khong', 'giup', 'minh', 'them', 'tim', 'muon', 'hien', 'tai', 'duoc', 'cua', 'tren', 'web', 'va', 'la']));
            $tokens = array_values(array_filter($tokens, fn ($token) => strlen($token) >= 3));
            $query = $this->shop->availableProductsQuery()->leftJoin('LOAISANPHAM as c', 'c.MALOAISP', '=', 'available_products.MALOAISP');
            if ($tokens) {
                foreach (array_slice($tokens, 0, 8) as $token) {
                    $query->where(fn ($q) => $q->where('available_products.TENSP', 'like', '%'.$token.'%')->orWhere('available_products.HANG', 'like', '%'.$token.'%')->orWhere('c.TENLOAISP', 'like', '%'.$token.'%'));
                }
            }
            $products = $query->orderBy('available_products.TENSP')->limit(5)->get();

            return $this->result($products->isEmpty() ? 'Không tìm thấy sản phẩm khớp tên bạn hỏi. Mở trang Sản phẩm để tìm theo tên hoặc nhóm.' : $products->map(function ($product): string {
                $price = $product->GIAKHUYENMAI === null ? (int) $product->DONGIA : min((int) $product->DONGIA, (int) $product->GIAKHUYENMAI);

                return '- '.$product->TENSP.': '.$this->money($price).', có thể đặt '.(int) $product->available_quantity.'.';
            })->join("\n")."\nGiá trước ưu đãi hạng khách; tồn được kiểm tra lại khi đặt hàng.", 'Sản phẩm, giá và lượng có thể đặt từ SQL');
        }
        if (preg_match('/^(xin chao|chao|hello|hi)[!. ]*$/', $text)) {
            return $this->result('Chào bạn! Mình tra cứu được gói tập, sản phẩm, lớp/PT, thanh toán, giao hàng và thông tin công khai của phòng tập.', 'Trợ giúp website');
        }

        return null;
    }

    /** @return array{answer: string, sources: list<string>}|null */
    public function answerPrivateAccountQuestion(string $question): ?array
    {
        if (! preg_match('/\b(cua toi|cua minh|mat khau cua|ma otp|token|du lieu khach|danh sach khach)\b/', $this->normalize($question))) {
            return null;
        }

        return $this->result('Trợ lý chỉ tra cứu thông tin công khai. Hãy đăng nhập và mở Đơn hàng, Gói hội viên hoặc Lịch tập để xem dữ liệu của chính bạn. Không gửi mật khẩu hay mã OTP vào chat.', 'Quyền riêng tư tài khoản');
    }

    private function normalize(string $text): string
    {
        return trim(Str::ascii(mb_strtolower($text)));
    }

    private function money(int $value): string
    {
        return number_format($value, 0, ',', '.').'đ';
    }

    private function result(string $answer, string $source): array
    {
        return ['answer' => $answer, 'sources' => [$source]];
    }
}
