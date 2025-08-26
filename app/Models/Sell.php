<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sell extends Model
{
    // 👈 IMPORTANT: singular table
    protected $table = 'sell';

    protected $fillable = [
        'buyer_id','sponsor_id','income_to_user_id','leg',
        'product','amount','income','income_type','level',
        'order_no','status','details','type'
    ];

    protected $casts = [
        'details' => 'array',
        'amount'  => 'decimal:2',
        'income'  => 'decimal:2',
    ];

    public function buyer(): BelongsTo    { return $this->belongsTo(\App\Models\User::class,'buyer_id'); }
    public function sponsor(): BelongsTo  { return $this->belongsTo(\App\Models\User::class,'sponsor_id'); }
    public function incomeTo(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'income_to_user_id'); }
}
