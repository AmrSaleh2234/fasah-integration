<?php

namespace AmrSaleh\FasahPay\Console;

use AmrSaleh\FasahPay\FasahPayManager;
use Illuminate\Console\Command;
use Throwable;

class TestAuthCommand extends Command
{
    protected $signature = 'fasah:test-auth {--connection= : sandbox | production (defaults to config)}';

    protected $description = 'Request a Fasah Pay JWT using the configured credentials';

    public function handle(FasahPayManager $fasah): int
    {
        try {
            $client = $fasah->connection($this->option('connection') ?: null);
            $client->tokens()->forget();
            $token = $client->tokens()->requestToken();

            $this->info("Token OK for [{$client->connection()->name}] (length ".strlen($token).')');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
