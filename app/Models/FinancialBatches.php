<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;



/**
 * Essa tabela é meio confusa de cara, mas:
 * Representa, para a wa, um boleto/lote de diárias
 * 
 * Essa tabela compreende um período de diárias e um valor pago por elas,
 * ao ser marcada como processando vai distribuir o dinheiro pra quem é dono dele:
 * Colaboradores, Líderes, Coordenadores e o Caixa da Empresa só recebe quando é confirmado o recebimento
 * 
**/
class FinancialBatches extends Model
{
    protected $table = 'financial_batches';

    protected $fillable = [
        'company_id',
        'total_amount',
        'remaining_amount',
        'period_start',
        'period_end',
        'status',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'period_start' => 'date', 
        'period_end' => 'date',
    ];

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class, 'financial_batch_id');
    }
    
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function dailyRate(): HasMany
    {
        return $this->hasMany(DailyRate::class, 'company_id', 'company_id')
                    ->whereColumn('daily_rate.start', '>=', 'financial_batches.period_start')
                    ->whereColumn('daily_rate.start', '<=', 'financial_batches.period_end');
    }

}