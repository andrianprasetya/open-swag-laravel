<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Database\Eloquent\Model;

class NoFactoryModel extends Model
{
    protected $fillable = ['name', 'email'];
}
