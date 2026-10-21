<?php

namespace Tests\Unit;

use App\Support\ChartPalette;
use PHPUnit\Framework\TestCase;

class ChartPaletteTest extends TestCase
{
    public function test_nice_scale_rounds_the_axis_up_to_a_readable_step(): void
    {
        $this->assertSame([30.0, 10.0], ChartPalette::niceScale(26));
        $this->assertSame([1000.0, 250.0], ChartPalette::niceScale(930));
        $this->assertSame([3.0, 1.0], ChartPalette::niceScale(3));
    }

    public function test_empty_series_still_produce_an_axis(): void
    {
        [$max, $step] = ChartPalette::niceScale(0);
        $this->assertGreaterThan(0, $max);
        $this->assertGreaterThan(0, $step);
    }

    public function test_categorical_palette_has_six_distinct_colours(): void
    {
        $this->assertCount(6, array_unique(ChartPalette::CATEGORICAL));
    }
}
