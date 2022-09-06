<?php

namespace Spatie\Period;

trait IterableImplementation
{
    protected $position = 0;

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->periods[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->periods[] = $value;

            return;
        }

        $this->periods[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->periods);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->periods[$offset]);
    }

    public function next(): void
    {
        $this->position++;
    }

    /**
     * @return mixed
     */
    public function key()
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return array_key_exists($this->position, $this->periods);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function count(): int
    {
        return count($this->periods);
    }
}
