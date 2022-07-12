<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TapdCheckRule extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'type', 'tag', 'detail',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    static public function Data() {
        $result = [];
        $data = self::all();
        foreach($data as $item) {
            if (isset($item['type'], $item['tag']) && !empty($item['type']) && !empty($item['tag'])) {
                $result[$item['type']][$item['tag']] = $item['title'];
            }
        }
        return $result;
    }

}
