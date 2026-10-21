<?php

namespace Internal\Pasak\Http\Middleware;

/**
 * Legacy alias for PasakTrackUserActivity.
 */
class TrackUserActivity extends PasakTrackUserActivity
{
}

if (! class_exists('Internal\Pasak\Http\Middleware\TrackUserActivity', false)) {
    class_alias(PasakTrackUserActivity::class, 'Internal\Pasak\Http\Middleware\TrackUserActivity');
}
