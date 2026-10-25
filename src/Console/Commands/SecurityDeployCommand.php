<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakDeployCommand.
 */
class SecurityDeployCommand extends PasakDeployCommand {}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\SecurityDeployCommand', false)) {
    class_alias(PasakDeployCommand::class, 'Internal\\Pasak\\Console\\Commands\\SecurityDeployCommand');
}
