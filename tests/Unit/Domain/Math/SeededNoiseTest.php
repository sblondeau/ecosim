<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Math;

use App\Domain\Math\SeededNoise;
use PHPUnit\Framework\TestCase;

final class SeededNoiseTest extends TestCase
{
    public function testUniformIsDeterministic(): void
    {
        self::assertSame(
            SeededNoise::uniform(2025, 42, 'temp'),
            SeededNoise::uniform(2025, 42, 'temp'),
        );
    }

    public function testUniformStaysWithinItsRange(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $value = SeededNoise::uniform(7, $i, 'x');
            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThan(1.0, $value);
        }
    }

    public function testCenteredStaysWithinItsRange(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $value = SeededNoise::centered(7, $i, 'x');
            self::assertGreaterThanOrEqual(-1.0, $value);
            self::assertLessThan(1.0, $value);
        }
    }

    public function testChannelsAreIndependent(): void
    {
        self::assertNotSame(
            SeededNoise::uniform(2025, 42, 'temp'),
            SeededNoise::uniform(2025, 42, 'cloud'),
            'The same (seed, index) must draw differently per channel.',
        );
    }

    public function testSmoothHitsTheControlPointValues(): void
    {
        // At a control-point index (multiple of the period), smooth() equals
        // that control point's uniform draw.
        self::assertSame(
            SeededNoise::uniform(2025, 3, 'cloud'),
            SeededNoise::smooth(2025, 12, 'cloud', 4),
        );
    }

    public function testSmoothMovesGraduallyBetweenControlPoints(): void
    {
        $maxStep = 0.0;
        $previous = SeededNoise::smooth(2025, 0, 'x', 5);
        for ($i = 1; $i < 365; ++$i) {
            $current = SeededNoise::smooth(2025, $i, 'x', 5);
            $maxStep = max($maxStep, abs($current - $previous));
            $previous = $current;
        }

        // Steepest smoothstep slope is 1.5/period = 0.3 per index here — far
        // below the jumps white noise would produce (up to ~1.0).
        self::assertLessThan(0.35, $maxStep);
    }
}
