<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

// Alias nama legasi: RespondsWithDashboard diarahkan ke tur utama
// RespondsWithPasakDashboard (bila compat.php belum sempat memasangnya).
if (! trait_exists('Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard', false)) {
    class_alias(RespondsWithPasakDashboard::class, 'Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard');
}
