<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;

final readonly class LabelDocument
{
    public string $extension;

    public function __construct(
        public string $contents,
        public string $mimeType,
        public LabelFormat $format,
    ) {
        $this->extension = strtolower($this->format->value);
    }
}
