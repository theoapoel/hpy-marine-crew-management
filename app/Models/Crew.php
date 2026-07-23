<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Crew extends Model
{
    protected $fillable = [
        'name', 'rank', 'nationality', 'gender', 'date_of_birth', 'seaman_book_no',
        'status', 'vessel_id', 'sign_on_date', 'contract_end_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'sign_on_date' => 'date',
        'contract_end_date' => 'date',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
