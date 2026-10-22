<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * A stable name for "this page" whatever its language or address: a listing is
 * "business:42" in Arabic and in English. It is signed in the page, so the
 * collector only ever counts pages the site really rendered.
 */
final class PageKey
{
    /** Route (without locale prefix) => the route parameter that identifies the page. */
    private const ENTITY_ROUTES = [
        'business.show' => ['business', 'business'],
        'categories.show' => ['category', 'category'],
        'locations.show' => ['municipality', 'location'],
        'offers.show' => ['offer', 'offer'],
        'guides.show' => ['guide', 'guide'],
        'pages.show' => ['page', 'page'],
        'directory.az.letter' => ['letter', 'az'],
    ];

    /** Pages not counted (forms, accounts, search results that change with every query). */
    private const SKIP = ['search', 'login', 'register', 'password.request', 'password.reset', 'verification.notice'];

    public static function current(): ?string
    {
        $route = Route::current();
        $name = $route?->getName();
        if ($name === null) {
            return null;
        }
        $name = (string) preg_replace('/^(ar|en)\./', '', $name);
        if (in_array($name, self::SKIP, true) || str_starts_with($name, 'account.') || str_starts_with($name, 'owner.') || str_starts_with($name, 'admin.')) {
            return null;
        }

        if (isset(self::ENTITY_ROUTES[$name])) {
            [$param, $prefix] = self::ENTITY_ROUTES[$name];
            $value = $route->parameter($param);
            $id = $value instanceof Model ? $value->getKey() : $value;

            return $id !== null && $id !== '' ? Str::limit($prefix.':'.Str::lower((string) $id), 120, '') : null;
        }

        return Str::limit($name, 120, '');
    }

    public static function sign(string $key): string
    {
        return substr(hash_hmac('sha256', 'page:'.$key, (string) config('app.key')), 0, 20);
    }

    public static function verify(?string $key, ?string $signature): bool
    {
        return is_string($key) && is_string($signature) && $key !== '' && strlen($key) <= 120 && hash_equals(self::sign($key), $signature);
    }

    public static function forBusiness(int $id): string
    {
        return 'business:'.$id;
    }
}
