<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TapdCheckData extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_external_filter_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sqa_id', 'summary', 'audit_status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $casts = [
        'filter_results' => 'array',
    ];

    protected $appends = ['auto_reasons'];

    public function getAutoReasonsAttribute() {
        $result = [];
        if (key_exists('filter_results', $this->attributes)) {
            $reasons = json_decode($this->attributes['filter_results'], true);
            
            $rules = TapdCheckRule::Data();
    
            $type = $this->attributes['type'] ?? '';
            
            if (!empty($reasons) && !empty($type)) {
                foreach($reasons as $k=>$v) {
                    if ($v === 1 && isset($rules[$type][$k])) {
                        $result[] = $rules[$type][$k];
                    }
                }
            }
        }
        return $result;
    }

}
