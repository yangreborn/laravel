<?php

namespace App\Http\Controllers\Api;

use App\Models\VersionFlowTool;
use App\Models\LdapUser;
use App\Http\Controllers\ApiController;
use App\Models\VersionFlow;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Request;

class FlowController extends ApiController
{
    public function unlinkList(Request $request){
        $keywords = $request->key ?? '';
        $result = VersionFlow::doesntHave('projectInfo')
            ->where('url', '<>', '')
            ->when(!empty($keywords), function ($query) use ($keywords){
                $query->where('url', 'like', "%$keywords%");
            })
            ->select(['id', 'url as name'])
            ->limit(12)
            ->orderBy('url')
            ->get();
        return $this->success('获取版本流未关联项目列表成功！', $result);
    }

    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function flowList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort ?? [];
        $field = key_exists('field', $sort) && !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = key_exists('order', $sort) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $model = VersionFlow::query()->orderBy($field, $order);
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('url', 'like', "%$search_text%");
            }
            if (!empty($search['name'])){
                $sqa_name = LdapUser::query()->where('name', $search['name'])->take(1)->value('mail');
                $model = $model->where('sqa_email', $sqa_name);
            }
        }
        $flow_list = $model->paginate($page_size);
        foreach ($flow_list as &$item){
            $item->project = $item->projectInfo &&
                    $item->projectInfo->project ?
                    $item->projectInfo->project->name : null;
            $item->tools;
            $item->department = $item->projectInfo &&
                        $item->projectInfo->project &&
                        $item->projectInfo->project->department ?
                        $item->projectInfo->project->department->name : null;
            $item->sqa = $item->sqa_email ? LdapUser::query()->where('mail', $item->sqa_email)->take(1)->value('name') : null;

        }
        return $this->success('列表获取成功!', $flow_list);
    }

    /**
     * sqa列表
     * @param Request $request
     * @return mixed
     */
    public function sqaList(Request $request)
    {
        $sqas = sqa();
        $sqas = array_map(function ($item) {
            return $item['name'];
        }, $sqas);
        return $this->success('获取SQA人员成功', $sqas);
    }
}
