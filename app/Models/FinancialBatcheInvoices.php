<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialBatcheInvoices extends Model
{
    protected $table = 'financial_batch_invoices';

    protected $fillable = [
        'invoice_number',
        'amount',
        'description',
        'received',
        'received_at',
        'financial_batch_id',
    ];

    protected $casts = [
        'received_at' => 'date',
        'received' => 'boolean',
        'amount' => 'decimal:2',
    ];

    /**
     * Uma Nota Fiscal pertence a um Lote Financeiro específico.
     */
    public function financialBatch(): BelongsTo
    {
        return $this->belongsTo(FinancialBatches::class, 'financial_batch_id');
    }
}