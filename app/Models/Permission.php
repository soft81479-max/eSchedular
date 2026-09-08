<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model {
    protected $table = 'permissions';

    protected $fillable = [
        'module_id','action','slug','label','is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roles() {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        );
    }

    public function module() {
        return $this->belongsTo(Module::class);
    }

    public function rolePermissions() {
        return $this->hasMany(RolePermission::class);
    }
}