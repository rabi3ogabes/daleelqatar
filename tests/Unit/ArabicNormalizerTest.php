<?php

namespace Tests\Unit;

use App\Support\Search\ArabicNormalizer;
use PHPUnit\Framework\TestCase;

class ArabicNormalizerTest extends TestCase
{
    public function test_hamza_forms_are_unified_to_bare_alef(): void
    {
        $this->assertSame('احمد اسلام ال', ArabicNormalizer::normalize('أحمد إسلام آل'));
    }

    public function test_taa_marbuta_and_alef_maqsura_are_normalised(): void
    {
        $this->assertSame('مدرسه مستشفي', ArabicNormalizer::normalize('مدرسة مستشفى'));
    }

    public function test_tashkeel_and_tatweel_are_removed_and_whitespace_collapsed(): void
    {
        $this->assertSame('مطعم كبير', ArabicNormalizer::normalize("  مَطْعَـــم   كَبِير  "));
    }

    public function test_latin_text_is_lower_cased(): void
    {
        $this->assertSame('café doha', ArabicNormalizer::normalize('Café DOHA'));
    }

    public function test_sql_expression_is_pure_ascii(): void
    {
        $sql = ArabicNormalizer::sql('name_ar');

        $this->assertStringStartsWith('translate(lower(name_ar), ', $sql);
        $this->assertSame(1, preg_match('/^[\x20-\x7E]+$/', $sql), 'The SQL must not contain raw non-ASCII characters');
        $this->assertStringContainsString('chr(1571)', $sql); // أ
    }
}
