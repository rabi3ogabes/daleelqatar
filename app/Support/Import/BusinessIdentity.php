<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\Business;
use App\Models\BusinessIdentifier;

/**
 * Keeps business_identifiers in step with a listing's contact details. An
 * identifier belongs to the first listing that claimed it, so a number that is
 * already taken never moves silently to another business.
 */
final class BusinessIdentity
{
    /** @return array<string, list<string>> type => values */
    public static function of(Business $business): array
    {
        $phones = [];
        foreach ([$business->phone, $business->whatsapp, ...(array) $business->extra_phones] as $raw) {
            if (($p = Normalizer::phone($raw)) !== null) {
                $phones[$p] = true;
            }
        }

        $emails = [];
        foreach ([$business->email, ...(array) $business->extra_emails] as $raw) {
            if (($e = Normalizer::email($raw)) !== null) {
                $emails[$e] = true;
            }
        }

        $domain = Normalizer::domain($business->website);

        return array_filter([
            'phone' => array_keys($phones),
            'email' => array_keys($emails),
            'domain' => $domain ? [$domain] : [],
        ]);
    }

    public static function sync(Business $business): void
    {
        $wanted = self::of($business);

        $rows = [];
        foreach ($wanted as $type => $values) {
            foreach ($values as $value) {
                $rows[] = ['business_id' => $business->id, 'type' => $type, 'value' => $value];
            }
        }
        if ($rows !== []) {
            BusinessIdentifier::query()->insertOrIgnore($rows);
        }

        // Drop identifiers this listing no longer uses.
        BusinessIdentifier::query()->where('business_id', $business->id)
            ->where(function ($q) use ($wanted) {
                $q->whereNotIn('type', array_keys($wanted) ?: ['-']);
                foreach ($wanted as $type => $values) {
                    $q->orWhere(fn ($w) => $w->where('type', $type)->whereNotIn('value', $values));
                }
            })
            ->delete();
    }
}
