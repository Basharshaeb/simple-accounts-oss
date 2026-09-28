<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'role',
        'role_id',
        'is_default',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'last_seen_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $appends = [
        'is_online',
    ];

    public const ONLINE_WINDOW_MINUTES = 5;

    protected function isOnline(): Attribute
    {
        return Attribute::get(fn (): bool => $this->is_active
            && $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subMinutes(self::ONLINE_WINDOW_MINUTES)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roleObj(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
