<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'referral_id',
        'refer_by',
        'parent_id',
        'position',
        'left_user_id',
        'right_user_id',
        'Password_plain',
        'sponsor_id', // ✅ correct column name
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Jis user ne mujhe sponsor kiya (users.sponsor_id -> users.id)
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * Left child mapping (users.left_user_id -> users.id)
     */
    public function leftChild(): BelongsTo
    {
        return $this->belongsTo(User::class, 'left_user_id');
    }

    /**
     * Right child mapping (users.right_user_id -> users.id)
     */
    public function rightChild(): BelongsTo
    {
        return $this->belongsTo(User::class, 'right_user_id');
    }

    /**
     * Mere direct referrals (dusre users jinke sponsor_id = $this->id)
     */
    public function directReferrals(): HasMany
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }
}
