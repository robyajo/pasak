<?php

namespace Internal\Pasak\Http\Middleware;

/**
 * Legacy alias for PasakInjectAnnouncementBanner.
 */
class InjectAnnouncementBanner extends PasakInjectAnnouncementBanner {}

if (! class_exists('Internal\Pasak\Http\Middleware\InjectAnnouncementBanner', false)) {
    class_alias(PasakInjectAnnouncementBanner::class, 'Internal\Pasak\Http\Middleware\InjectAnnouncementBanner');
}
