<?php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $table = '_payout'; // underscore wali table
    protected $fillable = ['user_id','amount','status','method','type','created_at','updated_at'];
    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
