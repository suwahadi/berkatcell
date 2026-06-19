<?php

declare(strict_types=1);

use App\Services\SettingService;
use App\Support\ImageWebp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return SettingService::get($key, $default);
    }
}

if (! function_exists('order_expiry_minutes')) {
    function order_expiry_minutes(): int
    {
        return max(1, (int) setting('expiry_order', 5));
    }
}

if (! function_exists('phone_intl_digits')) {
    function phone_intl_digits(?string $number): string
    {
        $digits = (string) preg_replace('/[^0-9]/', '', (string) $number);

        if ($digits === '') {
            return '';
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}

if (! function_exists('tel_href')) {
    function tel_href(?string $number): ?string
    {
        $digits = phone_intl_digits($number);

        return $digits === '' ? null : 'tel:+'.$digits;
    }
}

if (! function_exists('wa_href')) {
    function wa_href(?string $text = null): ?string
    {
        $digits = phone_intl_digits((string) setting('site_whatsapp', ''));

        if ($digits === '') {
            return null;
        }

        $text ??= 'Halo BerkatCell saya mau order, mohon info lebih lanjut';

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}

if (! function_exists('rupiah')) {
    function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}

if (! function_exists('tanggal_id')) {
    function tanggal_id(mixed $date): string
    {
        return Carbon::parse($date)->locale('id')->isoFormat('D MMMM YYYY HH:mm:ss');
    }
}

if (! function_exists('store_webp')) {
    function store_webp(UploadedFile $file, string $folder = 'products'): string
    {
        return ImageWebp::store($file, $folder);
    }
}

if (! function_exists('delete_webp')) {
    function delete_webp(?string $path): void
    {
        ImageWebp::delete($path);
    }
}
