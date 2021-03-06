<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AuthorityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthorityController extends Controller
{

    protected $auth;

    public function __construct(AuthorityService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param Request $request
     * @return \App\Repositories\AuthorityRepository[]|\Illuminate\Database\Eloquent\Collection
     * 权限分组列表
     */
    public function indexGroup(Request $request)
    {
        return $this->auth->indexAuthGroup($request);
    }

    /**
     * @param Request $request
     * 添加权限分组
     */
    public function storeGroup(Request $request)//添加分组
    {
        $this->addAuthGroupVerify($request);
        return $this->auth->addAuthGroup($request);
    }

    /**
     * @param Request $request
     * 编辑权限分组
     */
    public function editGroup(Request $request)//编辑分组
    {
        $this->editAuthGroupVerify($request);
        return $this->auth->editAuthGroup($request);
    }

    /**
     * 删除权限分组
     */
    public function deleteGroup(Request $request)//删除分组
    {
        return $this->auth->deleteAuthGroup($request);
    }

    /**
     * 权限分组添加form验证
     * @param $request
     */
    public function addAuthGroupVerify($request)
    {
        $this->validate($request, [
            'name' => [
                'required',
                Rule::unique('authority_groups', 'name')
                    ->ignore('id', $request->get('id', 0))
            ],
            'departments.*.department_id' => 'numeric',
            'departments.*.department_name' => '',
            'staff.*.staff_sn' => 'numeric',
            'staff.*.staff_name' => '',
        ], [], [
            'name' => '分组名称',
            'departments.*.department_id' => '部门id',
            'departments.*.department_name' => '部门名称',
            'staff.*.staff_sn' => '员工编号',
            'staff.*.staff_name' => '员工姓名',
        ]);
    }

    /**
     * 权限分组编辑form验证
     * @param $request
     */
    public function editAuthGroupVerify($request)
    {
        $this->validate($request, [
            'name' => [
                'required',
                Rule::unique('authority_groups', 'name')
                    ->whereNotIn('id',explode(' ',$request->route('id')))
                    ->ignore('id', $request->get('id', 0))
            ],
            'departments.*.department_id' => 'numeric',
            'departments.*.department_name' => '',
            'staff.*.staff_sn' => 'numeric',
            'staff.*.staff_name' => '',
        ], [], [
            'name' => '分组名称',
            'departments.*.department_id' => '部门id',
            'departments.*.department_name' => '部门名称',
            'staff.*.staff_sn' => '员工编号',
            'staff.*.staff_name' => '员工姓名',
        ]);
    }
}
