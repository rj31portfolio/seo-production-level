<?php

namespace App\Tenancy;

use App\Models\Agency;
use Closure;
use LogicException;

final class TenantContext
{
    private ?Agency $agency = null;

    public function set(Agency $agency): void
    {
        $this->agency = $agency;
    }

    public function clear(): void
    {
        $this->agency = null;
    }

    public function agency(): ?Agency
    {
        return $this->agency;
    }

    public function id(): int
    {
        return $this->agency?->id ?? throw new LogicException('Tenant context is required.');
    }

    public function wrap(Closure $callback): Closure
    {
        $agency = $this->agency ?? throw new LogicException('Tenant context is required.');

        return fn () => $this->run($agency, $callback);
    }

    public function run(Agency $agency, Closure $callback): mixed
    {
        $previous = $this->agency;
        $this->set($agency);
        try {
            return $callback();
        } finally {
            $this->agency = $previous;
        }
    }
}
