<?php
declare(strict_types=1);

namespace App\Example\Message;

final class Ping
{
    public function __construct(public readonly string $text)
    {
    }
}
