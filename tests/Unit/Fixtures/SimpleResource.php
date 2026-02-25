<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class SimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'title' => $this->title,
            'published' => true,
        ];
    }
}
