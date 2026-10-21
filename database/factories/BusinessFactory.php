<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Category;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test/development factory. Records are always flagged is_demo = true.
 *
 * @extends Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? Category::query()->create([
                'name_ar' => 'فئة', 'name_en' => 'Category', 'slug' => 'category-'.Str::random(6),
            ])->id,
            'municipality_id' => Municipality::query()->inRandomOrder()->value('id'),
            'name_ar' => 'نشاط '.fake()->unique()->numerify('####'),
            'name_en' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description_ar' => 'وصف تجريبي للنشاط التجاري لأغراض التطوير والاختبار.',
            'description_en' => 'Sample business description used for development and testing.',
            'email' => fake()->safeEmail(),
            'status' => BusinessStatus::Draft,
            'is_demo' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => BusinessStatus::Approved,
            'approved_at' => now(),
            'published_at' => now()->subMinutes(fake()->numberBetween(1, 10000)),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => BusinessStatus::Pending, 'submitted_at' => now()]);
    }

    /** Factories deliberately bypass $fillable for status columns used in tests. */
    public function newModel(array $attributes = [])
    {
        $model = parent::newModel();
        $model->forceFill($attributes);

        return $model;
    }
}
