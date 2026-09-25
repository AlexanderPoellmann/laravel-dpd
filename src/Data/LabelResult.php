<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

final readonly class LabelResult
{
    /**
     * @param  list<Label>  $labels
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $labels,
        public array $raw,
    ) {}

    public function successful(): bool
    {
        return $this->labels !== [] && array_all($this->labels, fn (Label $label): bool => $label->successful());
    }
}
