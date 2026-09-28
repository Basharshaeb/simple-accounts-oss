<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'currency_id',
        'rate_date',
        'rate',
    ];

    protected $casts = [
        'rate_date' => 'date:Y-m-d',
        'rate' => 'decimal:6',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
