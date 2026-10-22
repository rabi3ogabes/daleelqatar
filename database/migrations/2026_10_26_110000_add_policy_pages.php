<?php

use Database\Seeders\Content\PolicyPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the cookie policy, disclaimer, editorial policy and advertising
 * pages (pages an administrator already created are left untouched), and adds
 * the visitor-statistics section to the privacy policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PolicyPages::pages() as $slug => [$titleAr, $titleEn, $bodyAr, $bodyEn]) {
            if (! DB::table('pages')->where('slug', $slug)->exists()) {
                DB::table('pages')->insert([
                    'slug' => $slug, 'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                    'is_published' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $privacy = DB::table('pages')->where('slug', 'privacy')->first();
        if ($privacy !== null) {
            [$ar, $en] = PolicyPages::privacySection();
            $update = [];
            if (! str_contains((string) $privacy->body_ar, PolicyPages::PRIVACY_MARKER_AR)) {
                $update['body_ar'] = rtrim((string) $privacy->body_ar)."\n\n".$ar."\n";
            }
            if (! str_contains((string) $privacy->body_en, PolicyPages::PRIVACY_MARKER_EN)) {
                $update['body_en'] = rtrim((string) $privacy->body_en)."\n\n".$en."\n";
            }
            if ($update !== []) {
                DB::table('pages')->where('id', $privacy->id)->update($update + ['updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', array_keys(PolicyPages::pages()))->delete();
    }
};
