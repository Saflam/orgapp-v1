<?php

namespace Modules\Core\Services;

use InvalidArgumentException;
use Modules\Core\Contracts\NotificationChannel;

class NotificationChannelRegistry
{
    /** @var array<string, NotificationChannel> */
    private array $channels = [];

    public function register(NotificationChannel $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    public function get(string $name): NotificationChannel
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Notification channel [{$name}] is not registered.");
        }

        return $this->channels[$name];
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->channels);
    }
}
