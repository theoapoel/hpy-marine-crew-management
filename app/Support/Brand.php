<?php

namespace App\Support;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Facades\Cache;

/**
 * The logo of the company being worked in: its "Company Logo" in ERP HPY, served
 * through the ERP file proxy. A company without one, or no company yet (login and
 * the company picker), falls back to the HPY logo in public/images.
 */
class Brand
{
    private const TTL = 3600;

    /** The company's own logo from ERP HPY, if it has one. */
    public static function companyLogo(): ?string
    {
        $company = rescue(fn () => app(ErpnextClient::class)->company(), null, false);

        if (! $company) {
            return null;
        }

        $file = rescue(fn () => Cache::remember(
            'brand_logo:'.md5($company),
            self::TTL,
            fn () => app(ErpnextClient::class)->companyProfiles()[$company]['logo'] ?? '',
        ), '', false);

        return $file ? route('erp.file', ['path' => $file]) : null;
    }

    public static function logo(): string
    {
        return self::companyLogo() ?? route('brand.image', config('brand.default'));
    }

    public static function icon(): string
    {
        return self::companyLogo() ?? route('brand.image', config('brand.default_icon'));
    }

    /** Drop the cached logo, e.g. after it was changed in ERP HPY. */
    public static function forget(string $company): void
    {
        Cache::forget('brand_logo:'.md5($company));
    }
}
