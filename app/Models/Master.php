<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Master extends Base implements AuthenticatableContract
{
    use Authenticatable;

    public static $type = [
        0 => "其他",
        1 => "支付宝",
        2 => "银行",
    ];

    protected $appends = [
        "name"
    ];

    public function getNameAttribute()
    {
        return $this->username;
    }

    public function setNameAttribute()
    {
        unset($this->attributes['name']);
    }

    public function getAvatar()
    {
        $avatar = $this->avatar;

        if ($avatar) {
            if (! URL::isValidUrl($avatar)) {
                $avatar = Storage::disk(config('admin.upload.disk'))->url($avatar);
            }

            return $avatar;
        }

        return admin_asset(config('master.default_avatar') ?: '@admin/images/default-avatar.jpg');
    }

    public function isAdministrator(): bool
    {
        return true;
    }

    /**
     * 达人可以绑定多位分销者
     */
    public function distributors()
    {
        return $this->hasMany(Distributor::class, "master_id", "id");
    }

    /**
     * 上级
     */
    public function parent()
    {
        return $this->belongsTo(Master::class, "parent_id", "id");
    }

    /**
     * 下级
     */
    public function childrens()
    {
        return $this->hasMany(Master::class, "parent_id", "id");
    }
}
