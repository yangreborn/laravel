<?php

namespace App\Models;

use function GuzzleHttp\Psr7\str;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UserContact extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'introduction', 'to', 'cc',
    ];

    protected $appends = ['to_data', 'cc_data'];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
    ];

    public function setToAttribute($value){
        $value = is_array($value) ? array_map('intval', $value) : [];
        $this->attributes['to'] = json_encode($value);
    }

    public function getToDataAttribute(){
        $to = json_decode($this->attributes['to'], true);
        $data = DB::table('users')->select(['id', 'name', 'email'])->whereIn('id', $to)->get();
        $result = [];
        foreach ($data as $item){
            $item = [
                'id' => (string)$item->id,
                'name' => $item->name,
                'email' => $item->email,
                'sort' => array_search($item->id, $to),
            ];
            $result[] = $item;
        }
        if (sizeof($result) > 1){
            usort($result, function($a, $b){
                return $a['sort'] <=> $b['sort'];
            });
        }
        return $result;
    }

    public function setCcAttribute($value){
        $value = is_array($value) ? array_map('intval', $value) : [];
        $this->attributes['cc'] = json_encode($value);
    }

    public function getCcDataAttribute(){
        $cc = json_decode($this->attributes['cc'], true);
        $data = DB::table('users')->select(['id', 'name', 'email'])->whereIn('id', $cc)->get();
        $result = [];
        foreach ($data as $item){
            $item = [
                'id' => (string)$item->id,
                'name' => $item->name,
                'email' => $item->email,
                'sort' => array_search($item->id, $cc),
            ];
            $result[] = $item;
        }
        if (sizeof($result) > 1){
            usort($result, function($a, $b){
                return $a['sort'] <=> $b['sort'];
            });
        }
        return $result;
    }

}
