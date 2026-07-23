<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vessel extends Model
{
    protected $fillable = [
        'name', 'type', 'status', 'principal', 'crew_capacity', 'eta',
    ];

    /**
     * The principal this vessel is manned for.
     *
     * Named principalRecord() because this placeholder table already carries a plain
     * "principal" string column that the dashboard reads; an attribute of the same
     * name would shadow the relation.
     */
    public function principalRecord(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Principal::class, 'principal_id');
    }

    public function crews(): HasMany
    {
        return $this->hasMany(Crew::class);
    }
}
