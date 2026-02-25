<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Database\Eloquent\Model;

class SampleModel extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'balance',
        'is_active',
        'settings',
        'metadata',
        'tags',
        'birthday',
        'created_at',
        'login_count',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'age' => 'integer',
        'balance' => 'float',
        'is_active' => 'boolean',
        'settings' => 'json',
        'metadata' => 'array',
        'tags' => 'collection',
        'birthday' => 'date',
        'created_at' => 'datetime',
        'login_count' => 'timestamp',
    ];
}
