<?php

declare(strict_types=1);

namespace Helpers;

use RuntimeException;
use InvalidArgumentException;

final class Config
{
    private string $url;
    private ?string $login = null;
    private string $tokenFile;

    public function __construct(string $configFile)
    {
        if (!file_exists($configFile)) {
            throw new RuntimeException("Config file '$configFile' does not exist.");
        }

        $config = \yaml_parse_file($configFile);

        $this->url = $config['url'] ?? '';
        $this->login = $config['login'] ?? null;
        $this->tokenFile = $config['token_file'] ?? '';

        if (empty($this->url)) {
            throw new InvalidArgumentException("Config key 'url' is required.");
        }
        if (empty($this->tokenFile)) {
            throw new InvalidArgumentException("Config key 'token_file' is required.");
        }

        if (!str_starts_with($this->tokenFile, '/')) {
            $this->tokenFile = realpath(dirname($configFile)) . '/' . $this->tokenFile;
        }
        if (file_exists($this->tokenFile)) {
            $this->tokenFile = realpath($this->tokenFile);
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    public function __get(string $name): mixed
    {
        if (!isset($this->$name)) {
            throw new InvalidArgumentException("Config key '$name' does not exist.");
        }
        return $this->$name;
    }

    public function getToken(): ?string
    {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        return trim(file_get_contents($this->tokenFile));
    }

    public function saveToken(string $token): void
    {
        file_put_contents($this->tokenFile, $token);
    }
}
