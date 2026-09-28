<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Http\Request;

class SetCompanyContext
{
    private const LAST_SEEN_WRITE_INTERVAL_SECONDS = 60;

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $companyIdHeader = $request->header('X-Company-ID');

            if ($companyIdHeader) {
                $membership = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $companyIdHeader)
                    ->where('is_active', true)
                    ->first();

                if (! $membership && $user->role !== 'SUPER_ADMIN') {
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بالوصول إلى بيانات هذه الشركة.',
                    ], 403);
                }

                CompanyContextService::setCompanyId((int) $companyIdHeader);
            } else {
                $membership = CompanyUser::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->first();

                if ($membership) {
                    CompanyContextService::setCompanyId($membership->company_id);
                }
            }

            if ($membership && ! $request->is('api/v1/auth/logout')) {
                $this->touchLastSeen($membership);
            }
        }

        return $next($request);
    }

    private function touchLastSeen(CompanyUser $membership): void
    {
        $isFresh = $membership->last_seen_at
            && $membership->last_seen_at->gt(now()->subSeconds(self::LAST_SEEN_WRITE_INTERVAL_SECONDS));

        if ($isFresh) {
            return;
        }

        // Base query so presence pings don't bump updated_at.
        CompanyUser::query()->toBase()
            ->where('id', $membership->id)
            ->update(['last_seen_at' => now()]);
    }
}
