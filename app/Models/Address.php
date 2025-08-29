<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 'address'; // 👈 singular

    protected $fillable = [
        'user_id','name','phone','line1','line2','city','state','pincode','country','is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
