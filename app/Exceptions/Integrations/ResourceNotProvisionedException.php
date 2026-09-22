<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

/**
 * A referenced provider resource does not exist yet, so the caller could not be
 * served. Distinct from a provider transport or provisioning failure, but served
 * with the same 503 as the rest of the hierarchy.
 */
final class ResourceNotProvisionedException extends ExternalProvisioningException {}
