<?php
namespace App\Services;

use App\Models\ChineseFestival;
use App\Models\LdapDepartment;
use App\Models\LdapUser;
use App\Models\TapdBug;
use App\Models\TapdStory;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TapdAlertService {
    private $base_uri = 'https://dolphin-dev.kedacom.com';
    private $url;
    private $header = [
        'Content-Type' => 'application/json; charset=UTF-8',
    ];
    private $request_data = [];

    public function __construct() {
        $this->request_data = [
            'json' => [
                'dueDate' => Carbon::now()->toDateString(),
            ],
        ];
    }

    public function getData() {
        $stroy_result = $this->storyAlert();
        $bug_result = $this->bugAlert();
        return array_merge($stroy_result, $bug_result);
    }

    private function storyAlert() {
        $this->url = 'tapd-backend/quality/api/story/unqualified';
        $response = $this->response('POST');
        $data = $response['result'];
        $result = [];
        foreach($data as $item) {
            $unpass_list = $item['unPassList'];
            $ids = array_column($unpass_list, 'id');
            $due = array_combine($ids, array_column($unpass_list, 'dueTime'));
            $after_chunk = array_chunk($ids, 50);
            $chart = $item['chart'];
            $res = array_reduce($after_chunk, function ($prev, $curr) use($due, $chart) {
                $stories = TapdStory::query()
                    ->join('tapd_projects', 'tapd_projects.project_id', '=', 'tapd_stories.workspace_id')
                    ->join('tapd_status', 'tapd_stories.workspace_id', '=', 'tapd_status.workspace_id')
                    ->select(
                        'story_id AS tapd_id',
                        'tapd_stories.name AS title',
                        DB::Raw("'story' AS type"),
                        'tapd_projects.name AS project',
                        'tapd_stories.creator AS creator',
                        'tapd_stories.owner AS current_owner',
                        'tapd_stories.priority AS priority',
                        'tapd_status.project_value AS status'
                    )
                    ->where('tapd_status.status_type', 'story')
                    ->whereRaw('tapd_status.system_value = tapd_stories.status')
                    ->whereIn('story_id', $curr)
                    ->get()
                    ->map(function ($v) use($due, $chart) {
                        $v['due_time'] = $due[$v['tapd_id']];
                        $v['reason'] = $this->getReason($chart);
                        $v['tag'] = $this->getTag($v['due_time']);
                        $v['uid'] = $this->getUid($v);
                        $v['department'] = $this->getDepartment($v['uid']);
                        $v['priority'] = $this->getPriority($v);
                        return $v;
                    })
                    ->toArray();
                return array_merge($prev, $stories);
            }, []);
            $result = array_merge($result, $res);
        }
        $new = TapdStory::query()
            ->join('tapd_projects', 'tapd_projects.project_id', '=', 'tapd_stories.workspace_id')
            ->join('tapd_status', 'tapd_stories.workspace_id', '=', 'tapd_status.workspace_id')
            ->select(
                'story_id AS tapd_id',
                'tapd_stories.name AS title',
                DB::Raw("'story' AS type"),
                'tapd_projects.name AS project',
                'tapd_stories.creator AS creator',
                'tapd_stories.owner AS current_owner',
                'tapd_stories.priority AS priority',
                'tapd_status.project_value AS status',
                'tapd_stories.created AS created'
            )
            ->where('tapd_status.status_type', 'story')
            ->whereRaw('tapd_status.system_value = tapd_stories.status')
            ->where('tapd_projects.is_external', 1)
            ->where('tapd_stories.created', '>=', ChineseFestival::workday(1, Carbon::now()->toDateString(), 'back'))
            ->where('tapd_stories.created', '<', Carbon::now()->startOfDay())
            ->get()
            ->map(function ($item) {
                $item['tag'] = 'new';
                $item['uid'] = $this->getUid($item);
                $item['department'] = $this->getDepartment($item['uid']);
                $item['priority'] = $this->getPriority($item);
                return $item;
            })
            ->toArray();
        return array_merge($result, $new);
    }

    private function bugAlert() {
        $this->url = 'tapd-backend/quality/api/bug/unqualified';
        $response = $this->response('POST');
        $data = $response['result'];
        $result = [];
        foreach($data as $item){
            $unpass_list = $item['unPassList'];
            $ids = array_column($unpass_list, 'id');
            $due = array_combine($ids, array_column($unpass_list, 'dueTime'));
            $after_chunk = array_chunk($ids, 50);
            $chart = $item['chart'];
            $res = array_reduce($after_chunk, function ($prev, $curr) use($due, $chart) {
                $bugs = TapdBug::query()
                    ->join('tapd_projects', 'tapd_projects.project_id', '=', 'tapd_bugs.workspace_id')
                    ->join('tapd_status', 'tapd_bugs.workspace_id', '=', 'tapd_status.workspace_id')
                    ->select(
                        'bug_id AS tapd_id',
                        'tapd_bugs.title AS title',
                        DB::Raw("'bug' AS type"),
                        'tapd_projects.name AS project',
                        'tapd_bugs.reporter AS creator',
                        'tapd_bugs.current_owner AS current_owner',
                        'tapd_bugs.severity AS priority',
                        'tapd_status.project_value AS status'
                    )
                    ->where('tapd_status.status_type', 'bug')
                    ->whereRaw('tapd_status.system_value = tapd_bugs.status')
                    ->whereIn('bug_id', $curr)
                    ->get()
                    ->map(function ($v) use($due, $chart) {
                        $v['due_time'] = $due[$v['tapd_id']];
                        $v['reason'] = $this->getReason($chart);
                        $v['tag'] = $this->getTag($v['due_time']);
                        $v['uid'] = $this->getUid($v);
                        $v['department'] = $this->getDepartment($v['uid']);
                        $v['priority'] = $this->getPriority($v);
                        return $v;
                    })
                    ->toArray();
                return array_merge($prev, $bugs);
            }, []);
            $result = array_merge($result, $res);
        }
        $new = TapdBug::query()
            ->join('tapd_projects', 'tapd_projects.project_id', '=', 'tapd_bugs.workspace_id')
            ->join('tapd_status', 'tapd_bugs.workspace_id', '=', 'tapd_status.workspace_id')
            ->select(
                'bug_id AS tapd_id',
                'tapd_bugs.title AS title',
                DB::Raw("'bug' AS type"),
                'tapd_projects.name AS project',
                'tapd_bugs.reporter AS creator',
                'tapd_bugs.current_owner AS current_owner',
                'tapd_bugs.severity AS priority',
                'tapd_status.project_value AS status',
                'tapd_bugs.created AS created'
            )
            ->where('tapd_status.status_type', 'bug')
            ->whereRaw('tapd_status.system_value = tapd_bugs.status')
            ->where('tapd_projects.is_external', 1)
            ->where('tapd_bugs.created', '>=', ChineseFestival::workday(1, Carbon::now()->toDateString(), 'back'))
            ->where('tapd_bugs.created', '<', Carbon::now()->startOfDay())
            ->get()
            ->map(function ($item) {
                $item['tag'] = 'new';
                $item['uid'] = $this->getUid($item);
                $item['department'] = $this->getDepartment($item['uid']);
                $item['priority'] = $this->getPriority($item);
                return $item;
            })
            ->toArray();
        return array_merge($result, $new);
    }

    /**
     * 获取接口返回信息
     * @return Object|Exception 
     */
    private function response($method) {
        $client = new Client([
            'base_uri' => $this->base_uri,
            'headers' => $this->header,
        ]);
        try {
            $response = $client->request($method, $this->url, $this->request_data);
        } catch (\Throwable $th) {
            throw new \Exception('Api server error!');
        }
        
        if ($response->getStatusCode() === 200) {
            $response = json_decode($response->getBody()->getContents(), true);
            if ($response['code'] === '0') {
                return $response;
            } else {
                throw new \Exception('Api is not ready!');
            }
        } else {
            throw new \Exception('Can\'t reach api server!');
        }
    }

    private function getUser($name) {
        if (!empty($name)) {
            if (LdapUser::query()->where('name', $name)->count() === 1) {
                return LdapUser::query()->where('name', $name)->value('uid');
            }
            return DB::table('tapd_users')
                ->join('ldap_users', 'ldap_users.mail', '=', 'tapd_users.email')
                ->where('tapd_users.user_name', $name)
                ->where('tapd_users.email', 'like', '%@kedacom.com')
                ->where('tapd_users.email', '<>', '')
                ->value('uid');
        }
        return null;
    }

    // STORY_DEP_RESPONSE_RATE 部门需求响应达标率排名
    // STORY_DEP_OVER_DUE_RATE 部门需求逾期率排名
    // BUG_DEP_RESPONSE_RATE 部门缺陷响应达标率排名
    // BUG_DEP_OVER_DUE_RATE 部门缺陷逾期率排名
    private function getReason($chart) {
        $reason = '';
        switch($chart) {
            case 'STORY_DEP_RESPONSE_RATE':
            case 'BUG_DEP_RESPONSE_RATE':
                $reason = '待响应';
                break;
            case 'STORY_DEP_OVER_DUE_RATE':
            case 'BUG_DEP_OVER_DUE_RATE':
                $reason = '待解决';
                break;
        }
        return $reason;
    }

    private function getTag($due_time) {
        $tag = '';
        // 已延期
        if (Carbon::createFromTimeString($due_time) < Carbon::now()->startOfDay()) {
            $tag = 'delayed';
        }
        // 即将到期
        if (Carbon::createFromTimeString($due_time) > Carbon::now()->endOfDay()) {
            $tag = 'delaying';
        }
        // 当日到期
        if (
            Carbon::createFromTimeString($due_time) <= Carbon::now()->endOfDay() &&
            Carbon::createFromTimeString($due_time) >= Carbon::now()->startOfDay()
        ) {
            $tag = 'today';
        }

        return $tag;
    }

    private function getUid($data) {
        $uid = null;

        if (!empty($data['current_owner'])) {
            $arr = explode(';', $data['current_owner']);
            foreach($arr as $cell) {
                $uid = $this->getUser($cell);
                if (!empty($uid)) {
                    break;
                }
            }
        } elseif (!empty($data['creator'])) {
            $uid = $this->getUser($data['creator']);
        }

        return $uid;
    }

    private function getPriority($data) {
        if ($data['type'] === 'story') {
            $map = config('tapd.story_mapping_priority');
        }
        if ($data['type'] === 'bug') {
            $map = config('tapd.bug_mapping_severity');
        }
        return !empty($data['priority']) ? $map[$data['priority']] : '';
    }

    /**
     * 根据用户uid获取所属的各级部门id集合
     */
    private function getDepartment($uid) {
        $result = [];
        if (!empty($uid)) {
            $department_id = LdapUser::query()
                ->where('uid', $uid)
                ->where('status', 1)
                ->value('department_id');
            $departments = LdapDepartment::getAllParents($department_id);
            $result = LdapDepartment::query()
                ->whereIn('name', $departments)
                ->pluck('id')
                ->toArray();
            $result[] = $department_id;
        }
        return $result;
    }
}