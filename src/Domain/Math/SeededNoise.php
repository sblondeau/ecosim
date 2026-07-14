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
     * Standard deviation of the centered smooth-noise field, `(smooth()−0.5)×2`,
     * derived analytically. The value at phase t within a segment is
     * lerp(low, high, S(t)) of two iid U(0,1) control points with S = smoothstep;
     * its marginal variance is (1/12)·∫₀¹[(1−S)²+S²]dt = 0.7428/12, so the
     * centered (×2) field has variance 0.2476 and std √0.2476 ≈ 0.4976.
     *
     * Callers that want noise of a target standard deviation σ (e.g. a sourced
     * temperature-anomaly std) scale the centered field by σ / this — otherwise
     * the smoothstep shape would silently deliver only ~half the intended
     * spread. {@see self::smoothUnit()} does exactly that.
     */
    public const float SMOOTH_CENTERED_STD = 0.4976;

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

    /**
     * Smooth value noise centred on 0 and normalised to unit standard deviation,
     * so multiplying it by σ yields persistent multi-index regimes whose spread
     * actually has std σ. Same shape as {@see self::smooth()}, just recentred
     * and rescaled by {@see self::SMOOTH_CENTERED_STD}.
     *
     * @param int<1, max> $period
     */
    public static function smoothUnit(int $seed, int $index, string $channel, int $period): float
    {
        return (self::smooth($seed, $index, $channel, $period) - 0.5) * 2.0 / self::SMOOTH_CENTERED_STD;
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
