<?php

namespace Chatsaas\LaravelWidget\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name'];

    public $timestamps = true;
}
