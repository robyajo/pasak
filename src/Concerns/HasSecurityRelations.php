<?php

namespace Internal\Pasak\Concerns;

/**
 * Legacy trait alias for HasPasakSecurityRelations.
 */
trait HasSecurityRelations
{
    use HasPasakSecurityRelations;
}

if (! class_exists('Internal\Pasak\Concerns\HasSecurityRelations', false) && ! trait_exists('Internal\Pasak\Concerns\HasSecurityRelations', false)) {
    class_alias(HasPasakSecurityRelations::class, 'Internal\Pasak\Concerns\HasSecurityRelations');
}
