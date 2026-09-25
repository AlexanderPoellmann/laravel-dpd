<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Exceptions\ConfigurationException;

final readonly class Credentials
{
    public function __construct(
        public string $username,
        public string $passwordMd5,
        public string $client,
    ) {}

    public static function fromConfig(bool $requireClient = true): self
    {
        $username = trim((string) config('dpd.username', ''));
        $client = trim((string) config('dpd.client', ''));
        $passwordMd5 = trim((string) config('dpd.password_md5', ''));
        $password = (string) config('dpd.password', '');

        if ($passwordMd5 === '' && $password !== '') {
            $passwordMd5 = md5($password);
        }

        if ($username === '') {
            throw new ConfigurationException('DPD username is not configured. Set DPD_USERNAME.');
        }

        if ($passwordMd5 === '') {
            throw new ConfigurationException('DPD password is not configured. Set DPD_PASSWORD or DPD_PASSWORD_MD5.');
        }

        if ($requireClient && $client === '') {
            throw new ConfigurationException('DPD client number is not configured. Set DPD_CLIENT.');
        }

        return new self($username, $passwordMd5, $client);
    }

    /** @return array<string, string> */
    public function toArray(bool $withClient = true): array
    {
        $credentials = [
            'username' => $this->username,
            'password' => $this->passwordMd5,
        ];

        if ($withClient) {
            $credentials['mandant'] = $this->client;
        }

        return $credentials;
    }
}
