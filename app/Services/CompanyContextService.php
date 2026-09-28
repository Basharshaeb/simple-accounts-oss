<?php

namespace App\Services;

class CompanyContextService
{
    protected static ?int $currentCompanyId = null;

    public static function setCompanyId(?int $companyId): void
    {
        static::$currentCompanyId = $companyId;
    }

    public static function getCompanyId(): ?int
    {
        return static::$currentCompanyId;
    }

    public static function clear(): void
    {
        static::$currentCompanyId = null;
    }
}
