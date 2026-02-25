<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class OpenApiResponse
{
    /**
     * @param int $status HTTP status code
     * @param string $description Response description
     * @param string|null $resource Fully qualified API Resource class name
     * @param string|null $schema Fully qualified schema class name
     * @param bool $isArray Whether the response is an array of items
     */
    public function __construct(
        public int $status = 200,
        public string $description = '',
        public ?string $resource = null,
        public ?string $schema = null,
        public bool $isArray = false,
    ) {
    }
}
