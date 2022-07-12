<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TapdAlert extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tapd_id',
        'title',
        'type',
        'project',
        'creator',
        'current_owner',
        'priority',
        'status',
        'due_time',
        'reason',
        'tag',
        'uid',
        'department',
        'created',
    ];

    protected $casts = [
        'department' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    static public function personalData($uid, $date = '') {
        if (empty($uid)) {
            return [];
        }
        $date = !empty($date) ? Carbon::createFromTimeString($date) : Carbon::now();

        $data = self::query()
            ->where('created_at', '>=', $date->copy()->startOfDay())
            ->where('created_at', '<=', $date->copy()->endOfDay())
            ->where('uid', $uid)
            ->orderBy('type')
            ->orderBy('priority', 'desc')
            ->get()
            ->map(function ($item) {
                if ($item['type'] === 'story') {
                    $item['url'] = 'https://www.tapd.cn/' . substr($item['tapd_id'], 2, 8) . '/prong/stories/view/' . $item['tapd_id'];
                }
                if ($item['type'] === 'bug') {
                    $item['url'] = 'https://www.tapd.cn/' . substr($item['tapd_id'], 2, 8) . '/bugtrace/bugs/view/' . $item['tapd_id'];
                }
                return $item;
            })
            ->groupBy('tag')
            ->toArray();
        return $data;
    }

    static public function collectionData($id, $date = '') {
        if (empty($id)) {
            return [];
        }
        $date = !empty($date) ? Carbon::createFromTimeString($date) : Carbon::now();

        $data = self::query()
            ->where('created_at', '>=', $date->copy()->startOfDay())
            ->where('created_at', '<=', $date->copy()->endOfDay())
            ->orderBy('type')
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(function ($item) use($id) {
                return in_array($id, $item['department']);
            })
            ->map(function ($item) {
                if ($item['type'] === 'story') {
                    $item['url'] = 'https://www.tapd.cn/' . substr($item['tapd_id'], 2, 8) . '/prong/stories/view/' . $item['tapd_id'];
                }
                if ($item['type'] === 'bug') {
                    $item['url'] = 'https://www.tapd.cn/' . substr($item['tapd_id'], 2, 8) . '/bugtrace/bugs/view/' . $item['tapd_id'];
                }
                return $item;
            })
            ->groupBy('tag')
            ->toArray();
        return $data;
    }

}
