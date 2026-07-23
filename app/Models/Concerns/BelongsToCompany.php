<?php

namespace App\Models\Concerns;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Database\Eloquent\Builder;

/**
 * Local records belong to the company picked in the header, exactly like the ERP HPY
 * documents do. Every list goes through ofCompany(), so switching company in the
 * navbar changes what the whole app shows.
 */
trait BelongsToCompany
{
    /** Rows of the session company; rows without one stay visible so nothing is lost. */
    public function scopeOfCompany(Builder $query, ?string $company = null): Builder
    {
        $company ??= app(ErpnextClient::class)->company();

        if (blank($company)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($company) {
            $q->where('company', $company)->orWhereNull('company');
        });
    }
}
