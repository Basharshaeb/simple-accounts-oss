<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginLog extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function record(User $user, ?int $companyId, Request $request): self
    {
        return self::create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'ip_address' => self::clientIp($request),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }

    public static function clientIp(Request $request): ?string
    {
        // Behind Cloudflare, X-Forwarded-For can carry a client-supplied prefix; CF-Connecting-IP is set by the edge.
        $cloudflareIp = $request->header('CF-Connecting-IP');

        return filter_var($cloudflareIp, FILTER_VALIDATE_IP) ? $cloudflareIp : $request->ip();
    }
}
