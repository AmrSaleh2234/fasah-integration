<?php

namespace AmrSaleh\FasahPay\Auth;

use AmrSaleh\FasahPay\Exceptions\FasahPayException;
use AmrSaleh\FasahPay\Exceptions\RequestException;
use AmrSaleh\FasahPay\Support\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TokenManager
{
    public function __construct(private readonly Connection $connection) {}

    public function token(): string
    {
        return Cache::store($this->connection->cacheStore)->remember(
            $this->connection->tokenCacheKey(),
            $this->connection->tokenTtl,
            fn (): string => $this->requestToken(),
        );
    }

    public function forget(): void
    {
        Cache::store($this->connection->cacheStore)->forget($this->connection->tokenCacheKey());
    }

    /**
     * Always requests a fresh JWT (bypasses the cache).
     */
    public function requestToken(): string
    {
        $this->connection->assertComplete();

        $response = Http::timeout($this->connection->timeout)
            ->acceptJson()
            ->withHeaders([
                'X-Tabadul-Client-Id' => $this->connection->get('client_id'),
                'X-Tabadul-Client-Secret' => $this->connection->get('client_secret'),
            ])
            ->post((string) $this->connection->get('token_url'), [
                'username' => $this->connection->get('username'),
                'password' => $this->connection->get('password'),
            ]);

        if ($response->failed()) {
            $data = $response->json();

            throw new RequestException(
                $response->status(),
                'Fasah Pay token request failed: '.$response->status().' '.$response->body(),
                is_array($data) ? $data : null,
            );
        }

        $token = $response->json('token') ?? $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new FasahPayException('Fasah Pay token missing in response.');
        }

        return str_starts_with($token, 'Bearer ') ? substr($token, 7) : $token;
    }
}
