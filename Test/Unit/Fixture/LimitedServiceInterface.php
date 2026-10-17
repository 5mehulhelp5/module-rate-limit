<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Fixture;

/** A REST service registered in the tests' endpoint registry. */
interface LimitedServiceInterface
{
    public function create(): void;

    public function read(): void;
}
