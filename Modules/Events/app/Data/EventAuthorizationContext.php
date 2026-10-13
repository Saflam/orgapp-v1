<?php

namespace Modules\Events\Data;

use Modules\Core\Contracts\AuthorizationContext as AuthorizationContextContract;
use Modules\Events\Models\Event;

final readonly class EventAuthorizationContext implements AuthorizationContextContract
{
    public function __construct(
        private int $organization,
        private ?int $unit,
    ) {
    }

    public static function fromEvent(Event $event): self
    {
        return new self(
            (int) $event->organization_id,
            $event->scope?->value === 'unit' && $event->unit_id !== null
                ? (int) $event->unit_id
                : null,
        );
    }

    public function contextType(): string
    {
        return $this->unit === null ? 'organization' : 'unit';
    }

    public function contextId(): int|string
    {
        return $this->unit ?? $this->organization;
    }

    public function organizationId(): int
    {
        return $this->organization;
    }
}
