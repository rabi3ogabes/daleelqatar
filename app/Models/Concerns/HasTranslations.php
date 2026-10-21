<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Bilingual attributes stored as `<attribute>_ar` / `<attribute>_en` columns.
 *
 * With exactly two supported languages, paired columns stay indexable,
 * simple to validate and fast to query, while this trait gives every model
 * a single locale-aware accessor: $model->t('name').
 */
trait HasTranslations
{
    public function t(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $fallback = $locale === 'ar' ? 'en' : 'ar';

        $value = $this->getAttribute("{$attribute}_{$locale}");

        if ($value === null || $value === '') {
            $value = $this->getAttribute("{$attribute}_{$fallback}");
        }

        return $value === '' ? null : $value;
    }

    /** @return array{ar: ?string, en: ?string} */
    public function translations(string $attribute): array
    {
        return [
            'ar' => $this->getAttribute("{$attribute}_ar"),
            'en' => $this->getAttribute("{$attribute}_en"),
        ];
    }

    /** Name of the column for the current locale, e.g. "name_ar" — useful for ordering. */
    public static function localizedColumn(string $attribute, ?string $locale = null): string
    {
        return $attribute.'_'.($locale ?? app()->getLocale());
    }
}
