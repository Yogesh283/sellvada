<?php

// app/Models/Wallet.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $table = 'wallet'; // aapki table ka naam singular hai
    protected $fillable = ['user_id', 'amount,', 'type'];
    protected $attributes = ['amount' => 0, 'type' => 'main'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
