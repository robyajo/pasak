<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'avatar',
    ];

    /**
     * URL publik avatar (null bila belum ada).
     */
    public function avatarUrl(): ?string
    {
        if ($this->avatar === null || $this->avatar === '') {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }
}
