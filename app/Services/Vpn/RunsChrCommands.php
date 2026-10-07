<?php

namespace App\Services\Vpn;

interface RunsChrCommands
{
    /**
     * @return array{ok: bool, output: string, message: string}
     */
    public function run(string $command): array;
}
