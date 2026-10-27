<?php

namespace App\Services;

use App\Models\GymSetting;
use Illuminate\Support\Str;

class GymPublicInfoService
{
    public function answerQuestion(string $question): ?string
    {
        $normalized = $this->normalize($question);
        if (preg_match('/\b(?:cua toi|cua minh|tai khoan ca nhan|don hang cua toi|lich su tap cua toi)\b/u', $normalized)) {
            return null;
        }

        $settings = GymSetting::query()->find(1);
        if ($settings === null) {
            return null;
        }

        if (preg_match('/\b(?:gio mo cua|mo cua may gio|may gio mo cua|opening hours)\b/u', $normalized)
            && filled($settings->opening_hours)) {
            return 'Giờ mở cửa hiện được The Gym công bố là '.$settings->opening_hours.'.';
        }

        if (preg_match('/\b(?:dia chi|o dau|nam o dau|vi tri)\b/u', $normalized)
            && filled($settings->address)) {
            return 'Địa chỉ The Gym hiện được công bố là '.$settings->address.'.';
        }

        if (preg_match('/\b(?:lien he|so dien thoai|dien thoai|sdt|email)\b/u', $normalized)) {
            $contacts = array_filter([
                filled($settings->phone) ? 'số '.$settings->phone : null,
                filled($settings->email) ? 'email '.$settings->email : null,
            ]);

            if ($contacts !== []) {
                return 'Bạn có thể liên hệ The Gym qua '.implode(' và ', $contacts).'.';
            }
        }

        if (preg_match('/\b(?:tap thu|buoi tap thu)\b/u', $normalized)
            && preg_match('/\b(?:bao lau|may ngay|thoi luong|keo dai)\b/u', $normalized)
            && $settings->trial_days !== null) {
            return 'Thời lượng tập thử hiện được cấu hình là '.$settings->trial_days.' ngày. Việc xác nhận lịch và quyền tập thử cụ thể do nhân viên xử lý.';
        }

        if (preg_match('/\b(?:the gym la gi|gioi thieu the gym|gioi thieu phong tap)\b/u', $normalized)
            && filled($settings->introduction)) {
            return Str::limit(trim(strip_tags($settings->introduction)), 1200);
        }

        return null;
    }

    private function normalize(string $text): string
    {
        return Str::ascii(str_replace(['đ', 'Đ'], ['d', 'D'], mb_strtolower($text)));
    }
}
