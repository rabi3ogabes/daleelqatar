<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\Offer> */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title_ar' => 'عرض '.fake()->numerify('###'),
            'title_en' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'description_ar' => 'وصف العرض',
            'description_en' => 'Offer description',
            'discount_label_ar' => '20%',
            'discount_label_en' => '20% off',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'status' => OfferStatus::Approved,
            'is_active' => true,
        ];
    }

    public function newModel(array $attributes = [])
    {
        $model = parent::newModel();
        $model->forceFill($attributes);

        return $model;
    }
}
