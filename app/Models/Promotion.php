<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rank history entry. Recording one updates the person's current rank. */
class Promotion extends Model
{
    protected $fillable = ['personnel_id', 'from_rank_id', 'to_rank_id', 'effective_date', 'order_reference', 'remarks', 'created_by'];

    protected $casts = ['effective_date' => 'date'];

    public function fromRank()
    {
        return $this->belongsTo(Rank::class, 'from_rank_id');
    }

    public function toRank()
    {
        return $this->belongsTo(Rank::class, 'to_rank_id');
    }
}
