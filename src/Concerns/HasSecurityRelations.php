<?php

namespace Internal\Pasak\Concerns;

// Alias nama legasi: HasSecurityRelations diarahkan ke tur utama
// HasPasakSecurityRelations (bila compat.php belum sempat memasangnya).
if (! trait_exists('Internal\Pasak\Concerns\HasSecurityRelations', false)) {
    class_alias(HasPasakSecurityRelations::class, 'Internal\Pasak\Concerns\HasSecurityRelations');
}
