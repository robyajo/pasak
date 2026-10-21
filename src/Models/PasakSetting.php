<?php

namespace Internal\Pasak\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Internal\Pasak\Concerns\ResolvesPasakTable;

/**
 * Persisted key-value settings for Pasak.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PasakSetting extends Model
{
    use ResolvesPasakTable;

    public function getTable()
    {
        $configured = config('pasak.table_names.settings', 'pasak_settings');

        return static::resolvePasakTable('settings', $configured, 'security_settings', 'pasak_settings');
    }

    protected $fillable = [
        'key',
        'value',
    ];
}
