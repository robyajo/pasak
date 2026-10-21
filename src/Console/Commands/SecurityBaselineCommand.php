<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakBaselineCommand.
 */
class SecurityBaselineCommand extends PasakBaselineCommand {}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\SecurityBaselineCommand', false)) {
    class_alias(PasakBaselineCommand::class, 'Internal\\Pasak\\Console\\Commands\\SecurityBaselineCommand');
}
