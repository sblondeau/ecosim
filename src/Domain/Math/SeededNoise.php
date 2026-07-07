<?php

declare(strict_types=1);

namespace App\Domain\Math;

use function hash;
use function hexdec;
use function intdiv;
use function max;
use function substr;

/**
 * Deterministic pseudo-randomness for the simulation — the game's only source
 * of "chance". No RNG, no clock: a value is a pure function of
 * (seed, index, channel), so the same game replays identically and tests can
 * assert exact values (game-design §3).
 *
 * The channel string keeps independent uses (temperature, clouds, demand…)
 * uncorrelated for the same day. Two flavours:
 * - {@see self::uniform()} / {@see self::centered()}: white noise — each index
 *   draws independently (day-to-day events, e.g. household demand);
 * - {@see self::smooth()}: 1D value noise — random control points every N
 *   indices, smoothstep-interpolated between them, so values form persistent
 *   multi-day regimes (weather patterns) instead of daily zapping.
 */
final class SeededNoise
{
    /**
     * White noise in [0, 1).
     */
    public static function uniform(int $seed, int $index, string $channel): float
    {
        $digest = hash('sha256', $seed.':'.$index.':'.$channel);
        // 13 hex chars = 52 bits, exactly representable as a float mantissa.
        $bucket = hexdec(substr($digest, 0, 13));

        return $bucket / (16.0 ** 13);
    }

    /**
     * White noise in [-1, 1) — convenience for symmetric ± bands.
     */
    public static function centered(int $seed, int $index, string $channel): float
    {
        return (self::uniform($seed, $index, $channel) - 0.5) * 2.0;
    }

    /**
     * Smooth value noise in [0, 1): control points drawn every $period indices,
     * interpolated in between — consecutive indices give close values, forming
     * regimes that persist for a few indices before drifting to the next.
     *
     * @param int<1, max> $period
     */
    public static function smooth(int $seed, int $index, string $channel, int $period): float
    {
        $period = max(1, $period);
        $controlPoint = intdiv($index, $period);
        $t = (float) ($index - $controlPoint * $period) / $period;

        $low = self::uniform($seed, $controlPoint, $channel);
        $high = self::uniform($seed, $controlPoint + 1, $channel);

        return self::lerp($low, $high, self::smoothstep($t));
    }

    private static function lerp(float $a, float $b, float $t): float
    {
        return $a + ($b - $a) * $t;
    }

    /**
     * Ease-in/ease-out curve: zero slope at both ends, so consecutive noise
     * segments join without kinks.
     */
    private static function smoothstep(float $t): float
    {
        return $t * $t * (3.0 - 2.0 * $t);
    }
}
