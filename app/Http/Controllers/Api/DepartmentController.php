<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ApiException;
use App\Models\Department;
use App\Http\Controllers\ApiController;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends ApiController
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function departmentList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field'])&&!empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $type = $request->type; //列表数据：true为仅一级部门；false（默认）为仅二级部门
        $department_model = Department::orderBy($field, $order)->where('parent_id', $type ? '=' : '>', 0);
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $department_model = $department_model->where('name', 'like', "%$search_text%");
            }
            if (!empty($search['category'])){
                $category = $search['category'];
                $department_model = $department_model->where('parent_id', '=', $category);
            }
            $departments = $department_model->paginate($page_size);
        }else{
            $departments = $department_model->paginate($page_size);
        }
        foreach ($departments as $department){
            $department->parent_department = '';
            if ($department->parent_id){
                $department->parent_department = Department::where('id', $department->parent_id)->value('name');
            }
            $department->supervisor = '';
            if ($department->supervisor_id){
                $department->supervisor = DB::table('users')->where('id', $department->supervisor_id)->value('name');
            }
        }
        return $this->success('部门列表获取成功!', $departments);
    }

    public function search(Request $request)
    {
        $key = $request->key;
        $excepted = $request->excepted?:[];
        $type = $request->type; //搜索结果：true为仅一级部门；false（默认）为仅二级部门
        $departments = Department::where('name', 'like', "%$key%")
            ->where('parent_id', $type ? '=' : '<>', 0)
            ->whereNotIn('id', $excepted)
            ->get();
        return $this->success('部门列表获取成功!', $departments);
    }

    /**
     * 添加
     * @param Request $request
     * @throws ApiException
     * @return array
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:45',
            'introduction' => 'max:255',
            'parent_id' => 'numeric',
            'supervisor_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        Department::create($input);

        return $this->success('添加部门成功!');
    }

    /**
     * 修改
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function edit(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:45',
            'introduction' => 'max:255',
            'parent_id' => 'numeric',
            'supervisor_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $role = Department::find($request->id);

        $role->name = $request->name;
        $role->introduction = $request->introduction;
        $role->parent_id = $request->parent_id ? $request->parent_id : 0;
        $role->supervisor_id = $request->supervisor_id;

        $role->save();
        return $this->success('修改成功!');
    }

    /**
     * 角色删除
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function delete(Request $request){
        $department = Department::find($request->department_id);
        if ($department->parent_id === 0){
            $res =  Department::where('parent_id', '=', $department->id)->get();
            if ($res->count()){
                return $this->failed('删除部门失败,该部门仍有子部门!');
            }
        }
        $department->delete();
        return $this->success('删除部门成功!');
    }

    /**
     * 获取全部部门信息或顶级部门信息
     */
    public function getAllDepartments(Request $request){
        $type = $request->type ? true : false;
        $data = Department::where(
            [
                ['status', '=', 1],
                ['parent_id', $type ? '=' : '>=', 0],
            ])
            ->select('id as value', 'name as label', 'parent_id')
            ->get()
            ->toArray();
        $result = $this->formatData($data, 0);
        return $this->success('获取全部分类成功！', $result);
    }
    /**
     * 获取当前登陆者相关部门
     */
    public function getDepartments() {
        $all_departments = Department::where('status', 1)
            ->select('id as value', 'name as label', 'parent_id')
            ->get()
            ->toArray();
        $after_format = $this->formatData($all_departments, 0);
        $result = [];
        $user_id = Auth::guard('api')->id();
        if ($user_id) {
            $project_departments = Project::query()
                ->where('sqa_id', $user_id)
                ->pluck('department_id')
                ->unique()
                ->values()
                ->toArray();
            $after_filter = array_map(function($item) use($project_departments) {
                $children = $item['children'];
                $children_after_filter = array_filter($children, function($child) use($project_departments) {
                    return in_array($child['value'], $project_departments);
                });
                $item['children'] = array_values($children_after_filter);
                return  $item;
            }, $after_format);
            $result = array_values(array_filter($after_filter, function($item) {
                return sizeof($item['children']) > 0;
            }));
        }

        return $this->success('获取个人相关项目成功！', $result);
    }
    private function formatData($data, $pid){
        $result = [];
        foreach ($data as $key=>$item){
            if ($item['parent_id'] == $pid){
                unset($data[$key]);
                $item['children'] = $this->formatData($data, $item['value']);
                if ($item['children'] == null){
                    unset($item['children']);
                }
                unset($item['parent_id']);
                $result[] = $item;
            }else{
                continue;
            }
        }
        return $result;
    }
}
