<?php

// Backward-compatibility aliases: robyajo/pasak was previously
// published as robyajo/laravel-security-monitor under the
// Internal\SecurityMonitor namespace. Old host applications referencing
// those class names keep working via these aliases.

if (! class_exists('Internal\SecurityMonitor\PasakServiceProvider') && class_exists('Internal\Pasak\PasakServiceProvider')) {
    class_alias('Internal\Pasak\PasakServiceProvider', 'Internal\SecurityMonitor\SecurityMonitorServiceProvider');
}

if (! class_exists('Internal\SecurityMonitor\Facades\SecurityMonitor') && class_exists('Internal\Pasak\Facades\Pasak')) {
    class_alias('Internal\Pasak\Facades\Pasak', 'Internal\SecurityMonitor\Facades\SecurityMonitor');
}

if (! class_exists('Internal\SecurityMonitor\Services\SecurityMonitorService') && class_exists('Internal\Pasak\Services\PasakService')) {
    class_alias('Internal\Pasak\Services\PasakService', 'Internal\SecurityMonitor\Services\SecurityMonitorService');
}

if (! class_exists('Internal\SecurityMonitor\Services\PasakService') && class_exists('Internal\Pasak\Services\PasakService')) {
    class_alias('Internal\Pasak\Services\PasakService', 'Internal\SecurityMonitor\Services\PasakService');
}
