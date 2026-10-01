<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function expiryRules(): array
    {
        return self::find('client_expiry')?->value ?? ['renewal_days' => 30, 'expiring_days' => 14, 'urgent_days' => 6, 'reminders' => [30, 15, 7, 3, 1, 0]];
    }
}
