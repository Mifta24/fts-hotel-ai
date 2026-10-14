<?php

namespace App\Core\Member\Support;

/**
 * Every word a member reads, in each supported language. Kept in one place so
 * the sign-in mail, the sign-in pages and the account pages never drift apart.
 */
class MemberLabels
{
    public const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /**
     * @var array<string, array<string, string>>
     */
    private const LABELS = [
        'en' => [
            'login_title' => 'Member sign in', 'login_intro' => 'Enter your email and we will send you a 6-digit code. No password needed — new here? The same step creates your account.',
            'email' => 'Email', 'send_code' => 'Send me a code', 'keep_signed_in' => 'Keep me signed in on this device',
            'code_sent' => 'We sent a code to :email.', 'verify_title' => 'Enter your code', 'verify_intro' => 'Check the inbox of :email. The code works for :minutes minutes.',
            'code' => '6-digit code', 'verify' => 'Sign in', 'resend' => 'Send a new code', 'change_email' => 'Use another email',
            'code_invalid' => 'That code is not right or has expired. Please request a new one.', 'too_soon' => 'A code was just sent. Please wait a minute before asking for another.',
            'signed_in' => 'You are signed in.', 'signed_out' => 'You are signed out.',
            'account_title' => 'My account', 'hello' => 'Hello, :name', 'welcome' => 'Welcome! Tell us your name so the hotel can greet you.',
            'profile' => 'My details', 'name' => 'Name', 'phone' => 'Phone or WhatsApp (optional)', 'language' => 'Language', 'save' => 'Save', 'saved' => 'Your details were saved.',
            'bookings' => 'My reservations', 'no_bookings' => 'You have no reservations yet.', 'book_now' => 'Make a reservation',
            'reference' => 'Reference', 'room' => 'Room', 'stay' => 'Stay', 'nights' => 'nights', 'total' => 'Total', 'status' => 'Status',
            'pending' => 'Waiting for the hotel to confirm', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled',
            'check_in' => 'Check-in', 'check_out' => 'Check-out', 'reschedule' => 'Move my stay', 'new_dates' => 'Move to other dates',
            'cancel' => 'Cancel reservation', 'confirm_cancel' => 'Cancel this reservation? The rooms are released straight away.',
            'rescheduled' => 'Your stay was moved. The hotel will confirm the new dates.', 'cancelled_ok' => 'Your reservation was cancelled.',
            'unavailable' => 'Those dates are not available for this room. Please try other dates.', 'not_changeable' => 'This reservation can no longer be changed online. Please contact the hotel.',
            'check_in_past' => 'Check-in cannot be in the past.', 'check_out_after' => 'Check-out must be after check-in.', 'too_long' => 'Stays are limited to :max nights.',
            'invalid' => 'Please check this field.', 'logout' => 'Sign out', 'back' => 'Back to the hotel', 'member_link' => 'Member sign in', 'account_link' => 'My account',
            'mail_subject' => 'Your sign-in code for :brand', 'mail_intro' => 'Use this code to sign in to your :brand member account:', 'mail_expiry' => 'It works for :minutes minutes and only once.', 'mail_ignore' => 'If you did not ask for it, you can ignore this email — nobody can sign in without the code.',
        ],
        'id' => [
            'login_title' => 'Masuk member', 'login_intro' => 'Masukkan email Anda dan kami kirimkan kode 6 digit. Tanpa password — baru pertama kali? Langkah yang sama membuat akun Anda.',
            'email' => 'Email', 'send_code' => 'Kirim kode', 'keep_signed_in' => 'Tetap masuk di perangkat ini',
            'code_sent' => 'Kami mengirim kode ke :email.', 'verify_title' => 'Masukkan kode Anda', 'verify_intro' => 'Cek kotak masuk :email. Kode berlaku :minutes menit.',
            'code' => 'Kode 6 digit', 'verify' => 'Masuk', 'resend' => 'Kirim kode baru', 'change_email' => 'Pakai email lain',
            'code_invalid' => 'Kode salah atau sudah kedaluwarsa. Silakan minta kode baru.', 'too_soon' => 'Kode baru saja dikirim. Mohon tunggu semenit sebelum meminta lagi.',
            'signed_in' => 'Anda sudah masuk.', 'signed_out' => 'Anda sudah keluar.',
            'account_title' => 'Akun saya', 'hello' => 'Halo, :name', 'welcome' => 'Selamat datang! Isi nama Anda agar hotel bisa menyapa Anda.',
            'profile' => 'Data saya', 'name' => 'Nama', 'phone' => 'Telepon atau WhatsApp (opsional)', 'language' => 'Bahasa', 'save' => 'Simpan', 'saved' => 'Data Anda tersimpan.',
            'bookings' => 'Reservasi saya', 'no_bookings' => 'Anda belum punya reservasi.', 'book_now' => 'Buat reservasi',
            'reference' => 'Kode referensi', 'room' => 'Kamar', 'stay' => 'Menginap', 'nights' => 'malam', 'total' => 'Total', 'status' => 'Status',
            'pending' => 'Menunggu konfirmasi hotel', 'confirmed' => 'Terkonfirmasi', 'cancelled' => 'Dibatalkan',
            'check_in' => 'Check-in', 'check_out' => 'Check-out', 'reschedule' => 'Pindahkan jadwal', 'new_dates' => 'Pindah ke tanggal lain',
            'cancel' => 'Batalkan reservasi', 'confirm_cancel' => 'Batalkan reservasi ini? Kamar langsung dilepas.',
            'rescheduled' => 'Jadwal menginap dipindahkan. Hotel akan mengonfirmasi tanggal baru.', 'cancelled_ok' => 'Reservasi Anda dibatalkan.',
            'unavailable' => 'Tanggal tersebut tidak tersedia untuk kamar ini. Silakan coba tanggal lain.', 'not_changeable' => 'Reservasi ini tidak bisa diubah lagi secara online. Silakan hubungi hotel.',
            'check_in_past' => 'Tanggal check-in tidak boleh di masa lalu.', 'check_out_after' => 'Tanggal check-out harus setelah check-in.', 'too_long' => 'Lama menginap dibatasi :max malam.',
            'invalid' => 'Mohon periksa kolom ini.', 'logout' => 'Keluar', 'back' => 'Kembali ke hotel', 'member_link' => 'Masuk member', 'account_link' => 'Akun saya',
            'mail_subject' => 'Kode masuk Anda untuk :brand', 'mail_intro' => 'Pakai kode ini untuk masuk ke akun member :brand Anda:', 'mail_expiry' => 'Kode berlaku :minutes menit dan hanya sekali pakai.', 'mail_ignore' => 'Kalau Anda tidak memintanya, abaikan saja email ini — tanpa kode, tidak ada yang bisa masuk.',
        ],
        'ja' => [
            'login_title' => '会員ログイン', 'login_intro' => 'メールアドレスを入力すると、6桁のコードをお送りします。パスワードは不要です。初めての方は、同じ手順でアカウントが作成されます。',
            'email' => 'メールアドレス', 'send_code' => 'コードを送信', 'keep_signed_in' => 'この端末でログイン状態を保つ',
            'code_sent' => ':email にコードを送信しました。', 'verify_title' => 'コードを入力', 'verify_intro' => ':email の受信箱をご確認ください。コードの有効時間は:minutes分です。',
            'code' => '6桁のコード', 'verify' => 'ログイン', 'resend' => '新しいコードを送る', 'change_email' => '別のメールを使う',
            'code_invalid' => 'コードが正しくないか、有効期限が切れています。新しいコードをご請求ください。', 'too_soon' => 'コードを送信したばかりです。1分ほど待ってから再度お試しください。',
            'signed_in' => 'ログインしました。', 'signed_out' => 'ログアウトしました。',
            'account_title' => 'マイアカウント', 'hello' => ':name 様', 'welcome' => 'ようこそ！ホテルがご挨拶できるよう、お名前をお知らせください。',
            'profile' => 'ご登録情報', 'name' => 'お名前', 'phone' => '電話番号またはWhatsApp（任意）', 'language' => '言語', 'save' => '保存', 'saved' => '保存しました。',
            'bookings' => 'ご予約一覧', 'no_bookings' => 'ご予約はまだありません。', 'book_now' => '予約する',
            'reference' => '予約番号', 'room' => '客室', 'stay' => 'ご宿泊', 'nights' => '泊', 'total' => '合計', 'status' => '状況',
            'pending' => 'ホテルの確認待ち', 'confirmed' => '確認済み', 'cancelled' => 'キャンセル済み',
            'check_in' => 'チェックイン', 'check_out' => 'チェックアウト', 'reschedule' => '日程を変更', 'new_dates' => '日程を変更する',
            'cancel' => '予約をキャンセル', 'confirm_cancel' => 'このご予約をキャンセルしますか？客室はすぐに解放されます。',
            'rescheduled' => '日程を変更しました。ホテルが新しい日程を確認します。', 'cancelled_ok' => 'ご予約をキャンセルしました。',
            'unavailable' => 'この日程ではこの客室をご利用いただけません。別の日程をお試しください。', 'not_changeable' => 'このご予約はオンラインでは変更できません。ホテルまでご連絡ください。',
            'check_in_past' => 'チェックイン日は過去にできません。', 'check_out_after' => 'チェックアウト日はチェックインより後にしてください。', 'too_long' => 'ご宿泊は最大:max泊までです。',
            'invalid' => 'この項目をご確認ください。', 'logout' => 'ログアウト', 'back' => 'ホテルに戻る', 'member_link' => '会員ログイン', 'account_link' => 'マイアカウント',
            'mail_subject' => ':brand のログインコード', 'mail_intro' => ':brand の会員アカウントにログインするためのコードです：', 'mail_expiry' => '有効時間は:minutes分、1回のみ使用できます。', 'mail_ignore' => 'お心当たりがない場合は、このメールを無視してください。コードがなければ誰もログインできません。',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        return self::LABELS[in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'en'];
    }

    public static function supports(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    public static function fill(string $text, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':'.$key, (string) $value, $text);
        }

        return $text;
    }
}
