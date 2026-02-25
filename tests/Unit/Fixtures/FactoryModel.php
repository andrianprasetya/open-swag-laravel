<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryModel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'age'];

    protected static function newFactory(): FactoryModelFactory
    {
        return new FactoryModelFactory();
    }
}
