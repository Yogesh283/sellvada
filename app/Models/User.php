<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Wallet;

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
        'sponsor_id',
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
     * Auto-create a main wallet row when a user is created.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            Wallet::firstOrCreate(
                ['user_id' => $user->id, 'type' => 'main'],
                ['amount' => 0]
            );
        });
    }

    /**
     * Relations
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function leftChild(): BelongsTo
    {
        return $this->belongsTo(User::class, 'left_user_id');
    }

    public function rightChild(): BelongsTo
    {
        return $this->belongsTo(User::class, 'right_user_id');
    }

    public function directReferrals(): HasMany
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    /** Single main wallet (type = main) */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id')->where('type', 'main');
    }

    /** If you ever need multiple wallet types */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'user_id');
    }
}
