<?php

namespace Internal\Pasak\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted key-value settings for Pasak.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SecuritySetting extends Model
{
    public function getTable()
    {
        return config('pasak.table_names.settings', 'pasak_settings');
    }

    protected $fillable = [
        'key',
        'value',
    ];
}
