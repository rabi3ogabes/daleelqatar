<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugGenerator
{
    /** @param class-string<Model> $model */
    public function unique(string $model, string $source, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug(Str::limit($source, 120, ''));
        if ($base === '') {
            $base = Str::lower(Str::random(8));
        }

        $slug = $base;
        $i = 2;
        $query = fn (string $s) => $model::query()
            ->when(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true), fn ($q) => $q->withTrashed())
            ->where($column, $s)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        while ($query($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
