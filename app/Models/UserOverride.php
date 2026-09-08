<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOverride extends Model {
    protected $table = 'user_overrides';

    protected $fillable = [
        'user_id', 'permission_id', 'effect',
    ];

    public function permission() {
        return $this->belongsTo(
            Permission::class, 'permission_id'
        );
    }

    public function user() {
        return $this->belongsTo(
            User::class, 'user_id'
        );
    }
}