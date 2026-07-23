<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone at the principal we deal with: crewing superintendent, DPA, marine super. */
class PrincipalContactPerson extends Model
{
    /** Laravel would guess "principal_contact_people". */
    protected $table = 'principal_contact_persons';

    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }
}
