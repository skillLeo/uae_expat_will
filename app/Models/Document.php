<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Document extends Model implements HasMedia
{
    use InteractsWithMedia, RecordsActivity, SoftDeletes;

    /** The filename can itself be sensitive ("divorce-decree.pdf"). Not logged. */
    protected array $logAttributes = ['category', 'status'];

    protected $fillable = ['case_id', 'uploaded_by', 'category', 'status', 'review_note'];

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Uploads go to a PRIVATE disk and are served only through signed, expiring
     * URLs. There is no public disk registration anywhere in this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')
            ->useDisk('private')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg', 'image/png', 'image/heic', 'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('files');
    }
}
