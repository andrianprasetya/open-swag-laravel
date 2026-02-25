<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OpenApiRequestBody
{
    /**
     * @param string|null $formRequest Fully qualified Form Request class name
     * @param string|null $schema Fully qualified schema class name
     * @param string $contentType Request content type
     * @param bool $required Whether the request body is required
     * @param string $description Request body description
     */
    public function __construct(
        public ?string $formRequest = null,
        public ?string $schema = null,
        public string $contentType = 'application/json',
        public bool $required = true,
        public string $description = '',
    ) {
    }
}
