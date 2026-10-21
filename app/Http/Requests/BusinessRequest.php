<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SocialPlatform;
use App\Models\Area;
use App\Rules\SocialProfileUrl;
use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for creating/updating a listing — shared by the owner dashboard,
 * the admin dashboard and the REST API. Drafts may be incomplete; completeness
 * is enforced when the listing is submitted for review (ListingWorkflow).
 */
class BusinessRequest extends FormRequest
{
    /** Authorisation is handled by BusinessPolicy in the controllers. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trim = fn ($v) => is_string($v) ? trim($v) : $v;
        $website = $trim($this->input('website'));
        if ($website && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        $this->merge([
            'website' => $website ?: null,
            'phone' => $this->normalizePhone($this->input('phone')),
            'whatsapp' => $this->normalizePhone($this->input('whatsapp')),
            'email' => $this->filled('email') ? mb_strtolower($trim($this->input('email'))) : null,
            'social' => array_filter((array) $this->input('social', []), fn ($v) => filled($v)),
        ]);
    }

    public function rules(): array
    {
        $maxKb = (int) setting('uploads.max_image_kb', 5120);
        $image = ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.$maxKb, 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'];
        $time = ['nullable', 'date_format:H:i'];
        $partial = $this->isMethod('PATCH');
        $req = $partial ? 'sometimes' : 'required';

        $rules = [
            'name_ar' => [$req, 'string', 'min:2', 'max:160'],
            'name_en' => [$req, 'string', 'min:2', 'max:160'],
            'tagline_ar' => ['nullable', 'string', 'max:200'],
            'tagline_en' => ['nullable', 'string', 'max:200'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'category_id' => [$req, 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'category_ids' => ['sometimes', 'array', 'max:3'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('is_active', true)],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')->where('is_active', true)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'address_ar' => ['nullable', 'string', 'max:300'],
            'address_en' => ['nullable', 'string', 'max:300'],
            // Qatar's bounding box (with a small margin)
            'latitude' => ['nullable', 'numeric', 'between:24.3,26.3', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:50.6,51.8', 'required_with:latitude'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'website' => ['nullable', 'url:http,https', 'max:300'],
            'meta_title_ar' => ['nullable', 'string', 'max:160'],
            'meta_title_en' => ['nullable', 'string', 'max:160'],
            'meta_description_ar' => ['nullable', 'string', 'max:300'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],

            'social' => ['sometimes', 'array'],
            'hours' => ['sometimes', 'array'],
            'hours.*.closed' => ['nullable', 'boolean'],
            'hours.*.all_day' => ['nullable', 'boolean'],
            'hours.*.periods' => ['nullable', 'array', 'max:2'],
            'hours.*.periods.*.opens' => $time,
            'hours.*.periods.*.closes' => $time,
            'services' => ['sometimes', 'array', 'max:30'],
            'services.*.name_ar' => ['nullable', 'string', 'max:160'],
            'services.*.name_en' => ['nullable', 'string', 'max:160'],
            'services.*.description_ar' => ['nullable', 'string', 'max:500'],
            'services.*.description_en' => ['nullable', 'string', 'max:500'],
            'services.*.price_from' => ['nullable', 'numeric', 'min:0', 'max:9999999'],

            'logo' => $image,
            'cover' => $image,
        ];

        foreach (SocialPlatform::cases() as $platform) {
            $rules['social.'.$platform->value] = ['nullable', 'string', 'max:300', new SocialProfileUrl($platform)];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            // The area must belong to the selected municipality.
            if ($this->filled('area_id')) {
                $area = Area::query()->find($this->integer('area_id'));
                if ($area && $this->filled('municipality_id') && $area->municipality_id !== $this->integer('municipality_id')) {
                    $validator->errors()->add('area_id', __('validation.area_mismatch'));
                }
            }
            // Unknown social platforms are rejected rather than silently ignored.
            foreach (array_keys((array) $this->input('social', [])) as $key) {
                if (! SocialPlatform::tryFrom((string) $key)) {
                    $validator->errors()->add('social', __('validation.in', ['attribute' => 'social']));
                }
            }
        }];
    }

    /** Validated data in the shape expected by BusinessManager (files excluded). */
    public function businessData(): array
    {
        $data = collect($this->validated())->except(['logo', 'cover'])->all();
        if ($this->filled('area_id') && ! $this->filled('municipality_id')) {
            $data['municipality_id'] = Area::query()->whereKey($this->integer('area_id'))->value('municipality_id');
        }

        return $data;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $digits = preg_replace('/[^\d+]/', '', $value) ?? '';
        // Local 8-digit Qatar numbers get the +974 country code.
        if (preg_match('/^\d{8}$/', $digits)) {
            $digits = '+974'.$digits;
        }

        return $digits;
    }
}
