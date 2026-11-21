<?php

namespace App\Http\Controllers\Admin\Traits;

trait CacheaPanel
{
    protected function keyIndex(string $modulo): string
    {
        return "admin.{$modulo}.index";
    }

    protected function getIndex(string $modulo, callable $callback, int $ttl = 3600): mixed
    {
        return $callback();
    }

    protected function olvidaIndex(string $modulo): void {}
}
