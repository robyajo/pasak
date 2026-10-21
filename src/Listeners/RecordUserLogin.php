<?php

namespace Internal\Pasak\Listeners;

/**
 * Legacy alias for PasakRecordUserLogin.
 */
class RecordUserLogin extends PasakRecordUserLogin
{
}

if (! class_exists('Internal\Pasak\Listeners\RecordUserLogin', false)) {
    class_alias(PasakRecordUserLogin::class, 'Internal\Pasak\Listeners\RecordUserLogin');
}
