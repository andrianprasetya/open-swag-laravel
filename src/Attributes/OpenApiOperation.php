<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OpenApiOperation
{
    /**
     * @param string $summary Short summary of the operation
     * @param string $description Detailed description of the operation
     * @param string[] $tags List of tags for grouping
     * @param string[] $security List of security scheme names
     * @param bool $deprecated Whether the operation is deprecated
     */
    public function __construct(
        public string $summary = '',
        public string $description = '',
        public array $tags = [],
        public array $security = [],
        public bool $deprecated = false,
    ) {
    }
}
