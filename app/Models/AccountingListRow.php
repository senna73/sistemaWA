<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingListRow extends Model
{
    public const BUCKET_OK = 'ok';

    public const BUCKET_HIRED_AT = 'hired_at_mismatch';

    public const BUCKET_ONLY_LIST = 'only_list';

    public const BUCKET_ONLY_WA = 'only_wa';

    public const BUCKET_AMBIGUOUS = 'ambiguous';

    protected $fillable = [
        'accounting_list_check_id',
        'code',
        'name',
        'admission_on',
        'bucket',
        'collaborator_id',
        'candidate_ids',
        'wa_hired_on',
        'wa_code',
        'resolved_at',
        'resolved_by',
        'resolution',
        'snapshot',
    ];

    protected $casts = [
        'admission_on' => 'date',
        'wa_hired_on' => 'date',
        'candidate_ids' => 'array',
        'snapshot' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(AccountingListCheck::class, 'accounting_list_check_id');
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isOutOfStandard(): bool
    {
        return $this->bucket !== self::BUCKET_OK;
    }

    public function bucketLabel(): string
    {
        return match ($this->bucket) {
            self::BUCKET_OK => 'Bateu',
            self::BUCKET_HIRED_AT => 'Admissão diferente ou vazia',
            self::BUCKET_ONLY_LIST => 'Só na lista da contabilidade',
            self::BUCKET_ONLY_WA => 'Só na WA',
            self::BUCKET_AMBIGUOUS => 'Nome ambíguo / duplicado',
            default => $this->bucket,
        };
    }
}
