<?php

declare(strict_types=1);

namespace Temporal\Samples\FailedExample\Dto;

final readonly class WorkDto
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public Id $id,
        public WorkType $type,
        public string $tenant,
        public string $name,
        public array $tags,
    ) {
    }
}
