<?php

declare(strict_types=1);

namespace OpenSwag\Laravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class OpenApiParameter
{
    /**
     * @param string $name Parameter name
     * @param string $in Parameter location: path, query, header, cookie
     * @param string $description Parameter description
     * @param bool $required Whether the parameter is required
     * @param string $type Parameter type (string, integer, number, boolean, array)
     * @param mixed $example Example value
     */
    public function __construct(
        public string $name,
        public string $in = 'query',
        public string $description = '',
        public bool $required = false,
        public string $type = 'string',
        public mixed $example = null,
    ) {
    }
}
