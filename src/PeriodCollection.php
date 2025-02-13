<?php

namespace Spatie\Period;

use ArrayAccess;
use Closure;
use Countable;
use Iterator;

class PeriodCollection implements ArrayAccess, Iterator, Countable
{
    use IterableImplementation;

    /** @var \Spatie\Period\Period[] */
    protected array $periods;

    public static function make(Period ...$periods): static
    {
        return new static(...$periods);
    }

    public function __construct(Period ...$periods)
    {
        $this->periods = $periods;
    }

    public function current(): Period
    {
        return $this->periods[$this->position];
    }

    public function overlapAll(PeriodCollection ...$others): PeriodCollection
    {
        $overlap = clone $this;

        foreach ($others as $other) {
            $overlap = $overlap->overlap($other);
        }

        return $overlap;
    }

    public function boundaries(): ?Period
    {
        $start = null;
        $end = null;

        foreach ($this as $period) {
            if ($start === null || $start > $period->includedStart()) {
                $start = $period->includedStart();
            }

            if ($end === null || $end < $period->includedEnd()) {
                $end = $period->includedEnd();
            }
        }

        if (! $start || ! $end) {
            return null;
        }

        [$firstPeriod] = $this->periods;

        return Period::make(
            $start,
            $end,
            $firstPeriod->precision(),
            Boundaries::EXCLUDE_NONE()
        );
    }

    public function gaps(): static
    {
        $boundaries = $this->boundaries();

        if (! $boundaries) {
            return static::make();
        }

        return $boundaries->subtract(...$this);
    }

    public function intersect(Period $intersection): static
    {
        $intersected = static::make();

        foreach ($this as $period) {
            $overlap = $intersection->overlap($period);

            if ($overlap === null) {
                continue;
            }

            $intersected[] = $overlap;
        }

        return $intersected;
    }

    public function add(Period ...$periods): static
    {
        $collection = clone $this;

        foreach ($periods as $period) {
            $collection[] = $period;
        }

        return $collection;
    }

    public function map(Closure $closure): static
    {
        $collection = clone $this;

        foreach ($collection->periods as $key => $period) {
            $collection->periods[$key] = $closure($period);
        }

        return $collection;
    }

    public function reduce(Closure $closure, $initial = null): mixed
    {
        $carry = $initial;

        foreach ($this as $period) {
            $carry = $closure($carry, $period);
        }

        return $carry;
    }

    public function filter(Closure $closure): static
    {
        $collection = clone $this;

        $collection->periods = array_values(array_filter($collection->periods, $closure));

        return $collection;
    }

    public function isEmpty(): bool
    {
        return count($this->periods) === 0;
    }

    public function subtract(PeriodCollection | Period $others): static
    {
        if ($others instanceof Period) {
            $others = new static($others);
        }

        if ($others->count() === 0) {
            return clone $this;
        }

        $collection = new static();

        foreach ($this as $period) {
            $collection = $collection->add(...$period->subtract(...$others));
        }

        return $collection;
    }

    private function overlap(PeriodCollection $others): PeriodCollection
    {
        $overlaps = new PeriodCollection();

        foreach ($this as $period) {
            foreach ($others as $other) {
                if (! $period->overlap($other)) {
                    continue;
                }

                $overlaps[] = $period->overlap($other);
            }
        }

        return $overlaps;
    }

    public function unique(): PeriodCollection
    {
        $uniquePeriods = [];
        foreach ($this->periods as $period) {
            $uniquePeriods[$period->asString()] = $period;
        }

        return new static(...array_values($uniquePeriods));
    }

    public function sort(): PeriodCollection
    {
        $collection = clone $this;

        usort($collection->periods, static function (Period $a, Period $b) {
            return $a->includedStart() <=> $b->includedStart();
        });

        return $collection;
    }

    public function union(): PeriodCollection
    {
        $boundaries = $this->boundaries();

        if (! $boundaries) {
            return static::make();
        }

        return static::make($boundaries)
            ->subtract($boundaries->subtract(...$this));
    }

    /**
     * @param \DateTimeInterface|\DateTimeInterface[] $dates - must be sorted
     *
     * @return PeriodCollection
     */
    public function cut($dates): PeriodCollection
    {
        $periods = [];
        foreach ($this->periods as $period) {
            $periods = array_merge($periods, $period->cut($dates)->periods);
        }

        return static::make(...$periods);
    }

    public function cutOverlaps()
    {
        // Key by timestamp to ensure dates are unique.
        $dates = array_replace(...array_map(function ($period) {
            return [
                $period->start()->getTimestamp() => $period->start(),
                $period->end()->getTimestamp() => $period->end(),
            ];
        }, $this->periods));

        // Sort before passing to cut().
        ksort($dates);

        return $this->cut(array_values($dates));
    }

    public function mergeOverlaps(?Closure $closure = null): PeriodCollection
    {
        // Cut all overlapping regions and then group into start dates.
        $grouped_pieces = [];
        foreach ($this->cutOverlaps()->periods as $period) {
            $grouped_pieces[$period->asString()][] = $period;
        }

        // De-dupe pieces with array_reduce with the given closure function on the data.
        foreach ($grouped_pieces as $period_string => $periods) {
            $data = $closure ? array_reduce($periods, function ($data, $period) use ($closure) {
                return $closure($data, $period->data ?? []);
            }, []) : null;
            $grouped_pieces[$period_string] = Period::fromString($period_string, $data);
        }

        return static::make(...array_values($grouped_pieces));
    }

    public function join(?Closure $closure = null): PeriodCollection
    {
        // Default $closure is if period data is equal.
        $closure = $closure ?: function ($a, $b) {
            return $a['data'] == $b['data'];
        };

        // Key periods by start timestamp.
        $periods = $this->periods;
        $starts = array_map(function ($period) {
            return $period->start()->getTimestamp();
        }, $this->periods);
        $periods = array_combine($starts, $periods);
        ksort($starts);

        // Join contiguous periods.
        $joined = [];
        foreach ($starts as $start) {
            if ($period = $periods[$start] ?? null) {
                // If this end matches another start, and closure returns TRUE, join.
                $next_period = $period;
                while ($next_period !== null
                    && $closure($period, $next_period)
                ) {
                    // Join to original period, unset joined period and search again.
                    $joined_period = clone $next_period;
                    unset($periods[$joined_period->start()->getTimestamp()]);
                    $end = $joined_period->end()->getTimestamp();
                    $next_period = isset($periods[$end]) ? clone $periods[$end] : null;
                }

                $joined[] = Period::make($period->start(), $joined_period->end(), $period->precision(), $period->boundaries(), null, $period->data);
            }
        }

        return static::make(...array_values($joined));
    }

    public function toArray(): array
    {
        return $this->periods;
    }
}
