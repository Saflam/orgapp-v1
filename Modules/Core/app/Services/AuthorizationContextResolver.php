<?php

namespace Modules\Core\Services;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\Request;
use Modules\Core\Contracts\AuthorizationContext as AuthorizationContextContract;
use Modules\Core\Data\AuthorizationContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthorizationContextResolver
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function resolve(Request $request): AuthorizationContext
    {
        $organization = $this->currentOrganization->get();

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof AuthorizationContextContract) {
                continue;
            }

            if ($parameter->organizationId() !== $organization->id) {
                throw new NotFoundHttpException(
                    'Authorization context does not belong to the current organization.'
                );
            }

            return new AuthorizationContext(
                organizationId: $organization->id,
                unitId: $parameter->contextType() === 'unit'
                    ? (int) $parameter->contextId()
                    : null,
            );
        }

        return new AuthorizationContext(
            organizationId: $organization->id,
        );
    }
}