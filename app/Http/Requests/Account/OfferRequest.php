<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;

class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is enforced in the controller
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'business_id' => [$creating ? 'required' : 'prohibited', 'string'],
            'title_ar' => ['required', 'string', 'min:3', 'max:160'],
            'title_en' => ['required', 'string', 'min:3', 'max:160'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'discount_label_ar' => ['nullable', 'string', 'max:40'],
            'discount_label_en' => ['nullable', 'string', 'max:40'],
            'terms_ar' => ['nullable', 'string', 'max:2000'],
            'terms_en' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'after:now'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120)],
            'intent' => ['nullable', 'in:draft,submit'],
        ];
    }

    public function offerData(): array
    {
        $data = collect($this->validated())->only([
            'title_ar', 'title_en', 'description_ar', 'description_en', 'discount_label_ar', 'discount_label_en',
            'terms_ar', 'terms_en', 'starts_at', 'ends_at',
        ])->all();
        $data['is_active'] = $this->boolean('is_active', true);
        $data['save_as_draft'] = $this->input('intent') === 'draft';
        $data['submit'] = $this->input('intent') === 'submit';

        return $data;
    }
}
