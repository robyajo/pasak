<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakUnblockIpCommand.
 */
class UnblockIpAddress extends PasakUnblockIpCommand {}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\UnblockIpAddress', false)) {
    class_alias(PasakUnblockIpCommand::class, 'Internal\\Pasak\\Console\\Commands\\UnblockIpAddress');
}
