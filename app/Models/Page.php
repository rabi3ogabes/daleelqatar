<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title_ar', 'title_en', 'body_ar', 'body_en', 'meta_title_ar', 'meta_title_en',
        'meta_description_ar', 'meta_description_en', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** Markdown rendered safely: raw HTML is stripped and unsafe links are removed. */
    public function renderedBody(?string $locale = null): string
    {
        return Str::markdown((string) $this->t('body', $locale), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
