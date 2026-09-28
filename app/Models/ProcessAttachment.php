<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProcessAttachment extends Model
{
    protected $fillable = [
        'offboarding_process_id',
        'inactivity_audit_id',
        'candidate_id',
        'kind',
        'path',
        'original_name',
        'uploaded_by',
    ];

    public const KIND_LABELS = [
        'letter' => 'Carta a punho',
        'aso' => 'ASO',
        'waiver' => 'Declaração de dispensa',
        'mail' => 'Comprovante de correio',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(OffboardingProcess::class, 'offboarding_process_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }

    public function existsOnDisk(): bool
    {
        return $this->path && Storage::disk('local')->exists($this->path);
    }

    public function mimeType(): ?string
    {
        if (! $this->existsOnDisk()) {
            return null;
        }

        return Storage::disk('local')->mimeType($this->path) ?: null;
    }

    public function isImage(): bool
    {
        $mime = $this->mimeType();

        return $mime !== null && str_starts_with($mime, 'image/');
    }

    public function isPdf(): bool
    {
        $name = strtolower((string) $this->original_name);

        return $this->mimeType() === 'application/pdf' || str_ends_with($name, '.pdf');
    }
}
