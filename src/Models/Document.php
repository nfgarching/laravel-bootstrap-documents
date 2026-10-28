<?php

namespace Nfgarching\Documents\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Erzeugtes Dokument. Das PDF wird nicht mehr verändert; sha256 belegt den Stand
 * (z. B. für Download-Links des Mailers). Neue Fassung = neues Dokument mit version + 1.
 *
 * @property array|null $context
 * @property array|null $data
 */
class Document extends Model
{
    protected $table = 'nfdoc_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'context' => 'array',
        'data'    => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(DocumentLayout::class, 'layout_id');
    }

    public static function contextKey(array $context): ?string
    {
        if (! $context) {
            return null;
        }

        ksort($context);

        return collect($context)
            ->map(fn ($v, $k) => $k.':'.(is_scalar($v) ? $v : md5(json_encode($v))))
            ->implode('|');
    }

    public function scopeForContext(Builder $q, array $context): Builder
    {
        return $q->where('context_key', static::contextKey($context));
    }

    public function fileName(string $ext = 'pdf'): string
    {
        $name = trim(preg_replace('/[^\pL\pN\-_. ]+/u', '', $this->title)) ?: 'Dokument';

        return $name.($this->version > 1 ? " (Fassung {$this->version})" : '').'.'.$ext;
    }

    /** Prüft, ob das gespeicherte PDF unverändert ist */
    public function verify(): bool
    {
        $disk = Storage::disk($this->disk);

        return $disk->exists($this->path) && hash('sha256', $disk->get($this->path)) === $this->sha256;
    }

    /** Anhang-Angabe für nfmailer-open: ['disk','path','name'] */
    public function mailAttachment(): array
    {
        return ['disk' => $this->disk, 'path' => $this->path, 'name' => $this->fileName()];
    }
}
