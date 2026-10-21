<?php

namespace Internal\Pasak\Services;

/**
 * Legacy alias for PasakUserLoginService.
 */
class UserLoginService extends PasakUserLoginService {}

if (! class_exists('Internal\Pasak\Services\UserLoginService', false)) {
    class_alias(PasakUserLoginService::class, 'Internal\Pasak\Services\UserLoginService');
}
