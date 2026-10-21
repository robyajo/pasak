<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakUpgradeCommand.
 */
class SecurityUpgradeCommand extends PasakUpgradeCommand {}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\SecurityUpgradeCommand', false)) {
    class_alias(PasakUpgradeCommand::class, 'Internal\\Pasak\\Console\\Commands\\SecurityUpgradeCommand');
}
