<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix(env('API_VERSION').'/')->group(function(){

    Route::post('login', 'Api\UserController@login')->name('login|登录');
    Route::post('captcha', 'Api\UserController@captcha')->name('captcha|获取邮件验证码');
    Route::post('passwordForget', 'Api\UserController@passwordForget')->name('passwordForget|找回密码');
    Route::post('phab/duration', 'Api\PhabricatorController@reviewDuration')->name('phab-review-duration|phabricator代码有效性信息收集');
    Route::post('phab/api_data', 'Api\PhabricatorController@apiReportData')->name('pha-export-data|phabricator请求返回数据');

    /* 分享报告页面接口 */
    Route::post('report/data', 'Api\ReportController@getReportData')->name('report-data|获取报告数据');

    /* tapd外部需求&缺陷展示页面 */
    Route::post('tapd/notification', 'Api\TapdController@getNotificationData')->name('tapd-notification-data|获取tapd外部需求&缺陷数据');

    // tapd 每日提醒
    Route::post('tapd/alert', 'Api\TapdController@getAlertData')->name('tapd-alert-data|获取tapd每日提醒数据');

    /* wechat robot */
    Route::post('wechat/robot', function () {
        return event(new App\Events\ShareInfo('elk'));
    });

    /* 企业微信消息回调 */
    Route::get('wechat/callback', 'Api\WecomController@callback')->name('wecom-callback|企业微信消息回调');
    Route::post('wechat/callback', 'Api\WecomController@message')->name('wecom-callback|企业微信回调消息处理');

    /* TAPD webhook */
    Route::post('tapd/webhook', 'Api\TapdController@tapdWebhook')->name('tapd-webhook|tapd web hook');

    Route::middleware('auth:api')->group(function (){
        /* 用户接口 */
        Route::post('user/info', 'Api\UserController@info')->name('user-info|个人信息');
        Route::post('user/list', 'Api\UserController@userList')->name('user-list|人员列表');
        Route::post('user/add', 'Api\UserController@add')->name('user-add|人员添加');
        Route::post('user/edit', 'Api\UserController@edit')->name('user-edit|人员信息编辑');
        Route::post('user/reset', 'Api\UserController@passwordReset')->name('password-reset|密码重置');
        Route::post('user/delete', 'Api\UserController@userDelete')->name('user-delete|人员删除');
        Route::post('user/search', 'Api\UserController@search')->name('user-search|人员搜索');
        Route::post('user/profile', 'Api\UserController@profile')->name('user-profile|个人信息编辑');
        Route::post('user/password', 'Api\UserController@passwordModify')->name('user-modify-password|个人密码修改');
        Route::post('user/contact', 'Api\UserController@contactList')->name('user-contact|个人通信录');
        Route::post('user/contact/add', 'Api\UserController@contactAdd')->name('user-contact-add|个人通信录添加');
        Route::post('user/contact/edit', 'Api\UserController@contactEdit')->name('user-contact-edit|个人通信录编辑');
        Route::post('user/contact/delete', 'Api\UserController@contactDelete')->name('user-contact-delete|个人通信录删除');
        Route::post('user/project', 'Api\UserController@projectList')->name('user-project|个人项目集');
        Route::post('user/project/add', 'Api\UserController@projectAdd')->name('user-project-add|个人项目集添加');
        Route::post('user/project/edit', 'Api\UserController@projectEdit')->name('user-project-edit|个人项目集编辑');
        Route::post('user/project/delete', 'Api\UserController@projectDelete')->name('user-project-delete|个人项目集删除');
        Route::post('user/project/members', 'Api\UserController@projectsMembers')->name('user-project-members|各项目成员列表');
        Route::post('user/template', 'Api\UserController@templateList')->name('user-template|个人邮件模板');
        Route::post('user/template/edit', 'Api\UserController@templateEdit')->name('user-template-edit|个人邮件模板编辑');
        Route::post('user/template/delete', 'Api\UserController@templateDelete')->name('user-template-delete|个人邮件模板删除');
        Route::post('user/svn/unlinkList', 'Api\UserController@unlinkSvnUsers')->name('user-svn-unlink-list|未关联用户svn账号列表');
        Route::post('user/all', 'Api\UserController@allUsers')->name('user-all|全体人员列表');
        // Route::post('user/all', 'Api\LdapController@activeUsers')->name('user-all|全体人员列表');
        Route::post('user/logout', 'Api\UserController@logout')->name('user-logout|登出');

        /* LDAP相关接口 */
        Route::post('ldap/sqaDepartment', 'Api\LdapController@sqaDeaprtment')->name('sqa-department|sqa分管部门');
        Route::post('ldap/sqaDepartmentEdit', 'Api\LdapController@sqaDepartmentEdit')->name('sqa-department-edit|sqa分管部门编辑');



        /* 角色接口 */
        Route::post('role/list', 'Api\RoleController@roleList')->name('role-list|角色列表');
        Route::post('role/add', 'Api\RoleController@add')->name('role-add|角色添加');
        Route::post('role/edit', 'Api\RoleController@edit')->name('role-edit|角色编辑');
        Route::post('role/delete', 'Api\RoleController@delete')->name('role-delete|角色删除');

        /* 部门接口 */
        Route::post('department/list', 'Api\DepartmentController@departmentList')->name('department-list|部门列表');
        Route::post('department/add', 'Api\DepartmentController@add')->name('department-add|部门添加');
        Route::post('department/edit', 'Api\DepartmentController@edit')->name('department-edit|部门编辑');
        Route::post('department/delete', 'Api\DepartmentController@delete')->name('department-delete|部门删除');
        Route::post('department/search', 'Api\DepartmentController@search')->name('department-search|部门搜索');
        Route::post('department/all', 'Api\DepartmentController@getAllDepartments')->name('department-all|全体部门列表');
        Route::post('department/personal', 'Api\DepartmentController@getDepartments')->name('department-personal|个人部门列表');

        /* 项目接口 */
        Route::post('project/list', 'Api\ProjectController@projectList')->name('project-list|项目列表');
        Route::post('project/add', 'Api\ProjectController@add')->name('project-add|项目添加');
        Route::post('project/edit', 'Api\ProjectController@edit')->name('project-edit|项目编辑');
        Route::post('project/delete', 'Api\ProjectController@delete')->name('project-delete|项目删除');
        Route::post('project/search', 'Api\ProjectController@search')->name('project-search|项目搜索');
        Route::post('project/stageList', 'Api\ProjectController@stageList')->name('project-stage-list|项目阶段列表');
        Route::post('project/projectIndex', 'Api\ProjectController@getProjectIndex')->name('project-index|项目指数');
        Route::post('project/projectSetIndex', 'Api\ProjectController@setProjectIndex')->name('project-set-index|项目指数设置');
        Route::post('project/projectIndexData', 'Api\ProjectController@getProjectIndexData')->name('project-get-index-data|项目指数列表');
        Route::post('project/setTool', 'Api\ProjectController@setProjectTool')->name('project-set-tool|项目关联工具');
        Route::post('project/indexEdit', 'Api\ProjectController@indexEdit')->name('project-index-edit|期望指标编辑/指数填写');
        Route::post('project/weeklyAssessmentEdit', 'Api\ProjectController@weeklyAssessmentEdit')->name('project-weekly-assessment-edit|是否加入周报统计');
        Route::post('project/disassociate', 'Api\ProjectController@disassociate')->name('project-disassociate|取消工具关联');
        Route::post('project/classificationList', 'Api\ProjectController@classificationList')->name('project-classification-list|项目类型列表');

        /* 工具接口 */
        Route::post('tool/report/list', 'Api\ToolController@reportList')->name('tool-report-list|工具报告列表');
        Route::post('tool/report/delete', 'Api\ToolController@reportDelete')->name('tool-report-delete|工具报告删除');
        Route::post('tool/report/download', 'Api\ToolController@reportDownload')->name('tool-report-download|工具报告下载');
        Route::post('tool/deployAnalysis', 'Api\ToolController@deployAnalysis')->name('tool-deploy-analysis|工具部署情况分析');
        Route::post('2weeks/reportPreview','Api\TwoWeeksDataController@reportPreview')->name('reportPreview|度量双周报报告预览');
        Route::post('2weeks/reportSend','Api\TwoWeeksDataController@reportSend')->name('reportSend|度量双周报报告发送');
        Route::post('tool/getImageBase64', 'Api\ToolController@getImageBase64')->name('tool-image-base64|获取前端图片base64值');
        Route::post('tool/sharePage', 'Api\ToolController@sharePage')->name('tool-share-page|分享页面');
        Route::post('tool/shareInfo', 'Api\ToolController@shareInfo')->name('tool-share-info|分享信息');
        Route::post('tool/getLatestElkData', 'Api\ToolController@getLatestElkData')->name('tool-get-lastest-elk-data|获取最新elk数据');
        Route::post('tool/getLatestServerData', 'Api\ToolController@getLatestServerData')->name('tool-get-lastest-server-data|获取最新服务器数据');
        Route::post('tool/projectLinkedList', 'Api\ToolController@projectLinkedList')->name('project-linked-list|已关联静态检查项目列表');
        Route::post('tool/staticCheckedPreview', 'Api\ToolController@staticCheckedPreview')->name('static-data-preview|已关联静态检查项目列表信息预览');
        Route::post('tool/reportDataExport', 'Api\ToolController@reportDataExport')->name('tool-report-data-export|报告数据导出');

        /* PC-Lint 接口 */
        Route::post('pclint/list', 'Api\PclintController@pclintList')->name('pclint-list|pclint流列表');
        Route::post('pclint/add', 'Api\PclintController@add')->name('pclint-add|pclint流添加');
        Route::post('pclint/edit', 'Api\PclintController@edit')->name('pclint-edit|pclint流编辑');
        Route::post('pclint/delete', 'Api\PclintController@delete')->name('pclint-delete|pclint流删除');
        Route::post('pclint/data', 'Api\PclintController@lineChartData')->name('pclint-data|项目pclint检查数据列表');
        Route::post('pclint/current', 'Api\PclintController@currentLintData')->name('pclint-current-data|项目当前pclint检查数据');
        Route::post('pclint/ips', 'Api\PclintController@pclintIps')->name('pclint-ips|pclint服务器IP列表');
        Route::post('pclint/unlinkList', 'Api\PclintController@pclintUnlinkList')->name('pclint-unlink-list|未关联pclint流列表');
        Route::post('pclint/addIgnore', 'Api\PclintController@addIgnore')->name('pclint-ignore-edit|pclint 新增或者编辑屏蔽');
        Route::post('pclint/ignoreList', 'Api\PclintController@ignoreList')->name('pclint-ignore-list|pclint 屏蔽列表');
        Route::post('pclint/deleteIgnore', 'Api\PclintController@deleteIgnore')->name('pclint-ignore-delete|pclint 删除屏蔽');
        Route::post('pclint/ignoreSign', 'Api\PclintController@ignoreSign')->name('pclint-ignore-type|pclint 禁用标志');

        /* Phabricator 接口 */
        Route::post('phab/list', 'Api\PhabricatorController@phabList')->name('phab-list|phabricator流列表');
        Route::post('phab/add', 'Api\PhabricatorController@add')->name('phab-add|phabricator流添加');
        Route::post('phab/edit', 'Api\PhabricatorController@edit')->name('phab-edit|phabricator流编辑');
        Route::post('phab/delete', 'Api\PhabricatorController@delete')->name('phab-delete|phabricator流删除');
        Route::post('phab/ips', 'Api\PhabricatorController@phabIps')->name('phab-ips|phabricator服务器IP列表');
        Route::post('phab/unlinkList', 'Api\PhabricatorController@phabUnlinkList')->name('phab-unlink-list|未关联phabricator流列表');
        Route::post('phab/data', 'Api\PhabricatorController@reviewRate')->name('pha-line-data|phabricator评审率数据列表');
        Route::post('phab/projects', 'Api\PhabricatorController@getProjects')->name('pha-projects|指定部门的phabricator流列表');
        Route::post('phab/dataExport', 'Api\PhabricatorController@dataExport')->name('pha-data-export|phabricator原始数据导出');
        Route::post('phab/reportDataExport', 'Api\PhabricatorController@reportDataExport')->name('pha-report-data-export|phabricator报告数据导出');
        Route::post('phab/createPhabricatorJob', 'Api\PhabricatorController@CreatePhabricatorJob')->name('pha-create-job|创建phabricator job');

        /* diffcount 接口 */
        Route::post('diffcount/list', 'Api\DiffcountController@diffcountList')->name('diffcount-list|diffcount流列表');
        Route::post('diffcount/add', 'Api\DiffcountController@add')->name('diffcount-add|diffcount流添加');
        Route::post('diffcount/edit', 'Api\DiffcountController@edit')->name('diffcount-edit|diffcount流编辑');
        Route::post('diffcount/delete', 'Api\DiffcountController@delete')->name('diffcount-delete|diffcount流删除');
        Route::post('diffcount/ips', 'Api\DiffcountController@diffcountIps')->name('diffcount-ips|diffcount服务器IP列表');
        Route::post('diffcount/unlinkList', 'Api\DiffcountController@diffcountUnlinkList')->name('diffcount-unlink-list|未关联diffcount流列表');
        Route::post('diffcount/report','Api\DiffcountController@diffcountReport')->name('diffcountReport|diffcount报告数据预览');
        Route::post('diffcount/projects', 'Api\DiffcountController@getProjects')->name('diff-projects|指定部门diffcount流列表');
        Route::post('diffcount/reportPreview','Api\DiffcountController@reportPreview')->name('reportPreview|diffcount报告预览');
        Route::post('diffcount/reportSend','Api\DiffcountController@weekReportSend')->name('reportSend|diffcount报告发送');
        Route::post('diffcount/dataExport','Api\DiffcountController@reportDataExport')->name('dataExport|diffcount报告数据导出');
        Route::post('diffcount/reportConditions', 'Api\DiffcountController@reportConditions')->name('diffcount-report-conditions|diffcount报告搜索条件列表');

        /* plm 接口 */
        Route::post('plm/bugcount', 'Api\PlmController@getBugCount')->name('bug-count|项目plm数据列表');
        Route::post('plm/list', 'Api\PlmController@plmList')->name('plm-list|plm项目列表');
        Route::post('plm/add', 'Api\PlmController@add')->name('plm-add|plm项目添加');
        Route::post('plm/edit', 'Api\PlmController@edit')->name('plm-edit|plm项目编辑');
        Route::post('plm/delete', 'Api\PlmController@delete')->name('plm-delete|plm项目删除');
        Route::post('plm/projectUnlinkList', 'Api\PlmController@projectUnlinkList')->name('plm-project-unlink-list|未关联plm项目列表');
        Route::post('plm/projectLinkedList', 'Api\PlmController@projectLinkedList')->name('plm-project-linked-list|已关联plm项目列表');
        Route::post('plm/groupLinkInfo', 'Api\PlmController@groupLinkInfo')->name('plm-group-link-info|指定部门的plm小组关联信息');
        Route::post('plm/batchLinkGroup', 'Api\PlmController@batchLinkGroup')->name('plm-batch-link-group|plm小组关联部门');
        Route::post('plm/bugReportConfig', 'Api\PlmController@bugReportConfig')->name('plm-bug-report-config|plm报告固定参数列表');
        Route::post('plm/allPlmGroups', 'Api\PlmController@getAllPlmGroups')->name('plm-all-groups|全体plm小组列表');
        Route::post('plm/productList', 'Api\PlmController@productList')->name('plm-product-list|plm产品列表');
        Route::post('plm/productFamilyList', 'Api\PlmController@productFamilyList')->name('plm-product-family-list|plm产品族列表');
        Route::post('plm/projectSetList', 'Api\PlmController@projectSetList')->name('plm-project-set-list|plm项目集列表');
        Route::post('plm/projectSetSave', 'Api\PlmController@projectSetSave')->name('plm-project-set-save|plm项目集新增/编辑');
        Route::post('plm/projectSetDelete', 'Api\PlmController@projectSetDelete')->name('plm-project-set-delete|plm项目集删除');
        Route::post('plm/projectList', 'Api\PlmController@projectList')->name('plm-project-list|plm项目集中未选中项目列表');
        Route::post('plm/productSetList', 'Api\PlmController@productSetList')->name('plm-product-set-list|plm产品集列表');
        Route::post('plm/productSetSave', 'Api\PlmController@productSetSave')->name('plm-product-set-save|plm产品集新增/编辑');
        Route::post('plm/productSetDelete', 'Api\PlmController@productSetDelete')->name('plm-product-set-delete|plm产品集删除');
        Route::post('plm/setProductList', 'Api\PlmController@setProductList')->name('plm-set-product-list|plm产品集中未选中产品列表');
        Route::post('plm/groupSetList', 'Api\PlmController@groupSetList')->name('plm-group-set-list|plm小组集列表');
        Route::post('plm/groupSetSave', 'Api\PlmController@groupSetSave')->name('plm-group-set-save|plm小组集新增/编辑');
        Route::post('plm/groupSetDelete', 'Api\PlmController@groupSetDelete')->name('plm-group-set-delete|plm小组集删除');
        Route::post('plm/groupList', 'Api\PlmController@groupList')->name('plm-group-list|plm小组集中未选中小组列表');

        /* tapd 接口 */
        Route::post('tapd/list', 'Api\TapdController@tapdList')->name('tapd-list|tapd项目列表');
        Route::post('tapd/edit', 'Api\TapdController@edit')->name('tapd-edit|tapd项目编辑');
        Route::post('tapd/projectUnlinkList', 'Api\TapdController@projectUnlinkList')->name('tapd-project-unlink-list|未关联tapd项目列表');
        Route::post('tapd/projectLinkedList', 'Api\TapdController@projectLinkedList')->name('tapd-project-linked-list|已关联tapd项目列表');
        Route::post('tapd/bugReportConfig', 'Api\TapdController@bugReportConfig')->name('tapd-bug-report-config|tapd报告固定参数列表');
        Route::post('tapd/config', 'Api\TapdController@config')->name('tapd-config|tapd固定配置');
        Route::post('tapd/export', 'Api\TapdController@exportTapdBugData')->name('tapd-export|tapd数据导出');
        Route::post('tapd/customFields', 'Api\TapdController@getCustomFields')->name('tapd-custom-fields|tapd自定义字段列表');
        Route::post('tapd/checkRuleList', 'Api\TapdController@tapdCheckRuleList')->name('tapd-check-rule-list|tapd核查规则列表');
        Route::post('tapd/checkRuleSave', 'Api\TapdController@tapdCheckRuleSave')->name('tapd-check-rule-save|tapd核查规则修改');
        Route::post('tapd/checkRuleDelete', 'Api\TapdController@tapdCheckRuleDelete')->name('tapd-check-rule-delete|tapd核查规则删除');
        Route::post('tapd/checkDataList', 'Api\TapdController@tapdCheckDataList')->name('tapd-check-data-list|tapd核查数据列表');
        Route::post('tapd/checkDataEdit', 'Api\TapdController@tapdCheckDataEdit')->name('tapd-check-data-edit|tapd核查数据修改');

        /* 版本流接口 */
        Route::post('flow/unlinkList', 'Api\FlowController@unlinkList')->name('flow-unlink-list|未关联版本流列表');
        Route::post('flow/flowList', 'Api\FlowController@flowList')->name('flow-list|未关联版本流列表');
        Route::post('flow/sqaList', 'Api\FlowController@sqaList')->name('sqa-list|SQA人员列表');

        /* tscan code 接口 */
        Route::post('tscan/list', 'Api\TscanCodeController@jobList')->name('tscan-list|tscan job列表');
        Route::post('tscan/edit', 'Api\TscanCodeController@edit')->name('tscan-edit|tscan job编辑');
        Route::post('tscan/delete', 'Api\TscanCodeController@delete')->name('tscan-delete|tscan job删除');
        Route::post('tscan/tscanUnlinkList', 'Api\TscanCodeController@tscanUnlinkList')->name('tscan-unlink-list|未关联tscan job列表');
        Route::post('tscan/ips', 'Api\TscanCodeController@tscanIps')->name('tscan-ips|tscan ip列表');
        Route::post('tscan/addIgnore', 'Api\TscanCodeController@addIgnore')->name('tscan-ignore-edit|tscan 新增或者编辑屏蔽');
        Route::post('tscan/ignoreList', 'Api\TscanCodeController@ignoreList')->name('tscan-ignore-list|tscan 屏蔽列表');
        Route::post('tscan/deleteIgnore', 'Api\TscanCodeController@deleteIgnore')->name('tscan-ignore-delete|tscan 删除屏蔽');
        Route::post('tscan/ignoreType', 'Api\TscanCodeController@ignoreType')->name('tscan-ignore-list|tscan 屏蔽类型列表');

        /* pclint 邮件接口 */
        Route::post('pclint/weekReportPreview', 'Api\PclintController@weekReportPreview')->name('pclint-week-report-preview|pclint报告邮件预览');
        Route::post('pclint/weekReport', 'Api\PclintController@weekReport')->name('pclint-week-report|pclint报告发送');
        Route::post('pclint/weekReportData', 'Api\PclintController@weekReportData')->name('pclint-week-report-data|pclint报告数据预览');
        Route::post('pclint/reportConditions', 'Api\PclintController@reportConditions')->name('pclint-report-conditions|pclint报告搜索条件列表');

        /* phabricator 邮件接口 */
        Route::post('phab/reportOverview', 'Api\PhabricatorController@weekReportData')->name('phab-report-data|code review报告数据预览');
        Route::post('phab/reportPreview', 'Api\PhabricatorController@weekReportPreview')->name('phab-report-preview|code review报告邮件预览');
        Route::post('phab/reportSend', 'Api\PhabricatorController@weekReportSend')->name('phab-report-send|code review报告发送');
        Route::post('phab/reportConditions', 'Api\PhabricatorController@reportConditions')->name('pha-report-conditions|phabricator报告搜索条件列表');

        /* plm 邮件接口 */
        Route::post('plm/reportOverview', 'Api\PlmController@plmReportData')->name('plm-report-data|plm报告数据预览');
        Route::post('plm/reportPreview', 'Api\PlmController@plmReportPreview')->name('plm-report-preview|plm报告邮件预览');
        Route::post('plm/reportSend', 'Api\PlmController@plmReport')->name('plm-report-send|plm报告发送');
        Route::post('plm/reportExport', 'Api\PlmController@plmReportExport')->name('plm-report-export|plm报告导出');
        Route::post('plm/reportConditions', 'Api\PlmController@reportConditions')->name('plm-report-conditions|plm报告搜索条件列表');

        /* plm bug 延期处理报告接口 */
        Route::post('plm/bugProcessReportPreview', 'Api\PlmController@bugProcessReportPreview')->name('plm-bug-process-report-preview|plm bug处理报告预览');
        Route::post('plm/bugProcessReport', 'Api\PlmController@bugProcessReport')->name('plm-bug-process-report|plm bug处理报告发送');

        /* tapd bug 延期处理报告接口 */
        Route::post('tapd/bugProcessReportPreview', 'Api\TapdController@bugProcessReportPreview')->name('tapd-bug-process-report-preview|tapd bug处理报告预览');
        Route::post('tapd/bugProcessReport', 'Api\TapdController@bugProcessReport')->name('tapd-bug-process-report|tapd bug处理报告发送');
        Route::post('tapd/reportConditions', 'Api\TapdController@reportConditions')->name('tapd-bug-process-report-conditions|tapd报告搜索条件列表');

        /* tapd bug 单周处理报告接口 */
        Route::post('tapd/bugWeekReportConfig', 'Api\TapdController@bugWeekReportConfig')->name('tapd-bug-report-config|tapd报告固定参数列表');
        Route::post('tapd/bugWeekReportPreview', 'Api\TapdController@bugWeekReportPreview')->name('tapd-bug-report-config|tapd报告主体');

        /* tscan邮件接口 */
        Route::post('tscan/reportData', 'Api\TscanCodeController@reportData')->name('tscan-report-data|tscan报告数据预览');
        Route::post('tscan/reportPreview', 'Api\TscanCodeController@reportPreview')->name('tscan-report-preview|tscan报告邮件预览');
        Route::post('tscan/sendReport', 'Api\TscanCodeController@sendReport')->name('tscan-report-send|tscan报告发送');
        Route::post('tscan/reportConditions', 'Api\TscanCodeController@reportConditions')->name('tscan-report-conditions|tscan报告搜索条件列表');

        /* 报告页面接口 */
        Route::post('report/condition', 'Api\ReportController@getReportCondition')->name('report-condition|获取报告列表');
        Route::post('report/setCondition', 'Api\ReportController@setReportCondition')->name('set-report-condition|设置报告搜索条件');
        Route::post('report/summary', 'Api\ReportController@setReportSummary')->name('report-summary|更新报告总结');
        Route::post('report/send', 'Api\ReportController@sendEmail')->name('report-send|发送报告');
        Route::post('report/triggerRobot', 'Api\ReportController@triggerRobot')->name('report-trigger-robot|触发机器人');
        Route::post('report/refresh', 'Api\ReportController@refreshData')->name('report-refresh|刷新报告数据');
        Route::post('report/close', 'Api\ReportController@closeReport')->name('report-close|删除报告');
        Route::post('report/setComprehensiveExplain', 'Api\ReportController@setComprehensiveSummary')->name('report-comprehensive|综合报告更新');

        /* 命令页面接口 */
        Route::post('command/list', 'Api\CommandController@commandList')->name('command-list|命令列表');
        Route::post('command/run', 'Api\CommandController@commandRun')->name('command-run|命令运行');

        /* Dashboard 页面接口 */
        Route::post('dashboard/projectBriefInfo', 'Api\DashboardController@projectInfo')->name('dashboard-project-brief-info|Dashboard项目简短信息');
        Route::post('dashboard/projectMoreInfo', 'Api\DashboardController@projectMoreInfo')->name('dashboard-project-more-info|Dashboard项目更多信息');
        Route::post('dashboard/bugBriefInfo', 'Api\DashboardController@bugInfo')->name('dashboard-bug-brief-info|Dashboard缺陷简短信息');
        Route::post('dashboard/bugMoreInfo', 'Api\DashboardController@bugMoreInfo')->name('dashboard-bug-more-info|Dashboard缺陷更多信息');
        Route::post('dashboard/toolBriefInfo', 'Api\DashboardController@toolInfo')->name('dashboard-tool-brief-info|Dashboard工具简短信息');
        Route::post('dashboard/toolMoreInfo', 'Api\DashboardController@toolMoreInfo')->name('dashboard-tool-more-info|Dashboard工具更多信息');
        Route::post('dashboard/mailBriefInfo', 'Api\DashboardController@mailInfo')->name('dashboard-mail-brief-info|Dashboard邮件简短信息');
        Route::post('dashboard/mailMoreInfo', 'Api\DashboardController@mailMoreInfo')->name('dashboard-mail-more-info|Dashboard邮件更多信息');
        Route::post('dashboard/dashBoardStaticCheckInfo', 'Api\DashboardController@dashBoardStaticCheckInfo')->name('dashboard-static-check-info|Dashboard静态检测简短信息');
        Route::post('dashboard/dashBoardCodeLineInfo', 'Api\DashboardController@dashBoardCodeLineInfo')->name('dashboard-code-line-info|Dashboard代码行简短信息');
        Route::post('dashboard/dashBoardStaticCheckMoreInfo', 'Api\DashboardController@dashBoardStaticCheckMoreInfo')->name('dashboard-static-check-more-info|Dashboard静态检测详细信息');
        Route::post('dashboard/dashBoardCodeReviewIntimeInfo', 'Api\DashboardController@dashBoardCodeReviewIntimeInfo')->name('dashboard-codereview-intime-info|Dashboard代码评审及时率简短信息');
        Route::post('dashboard/dashBoardCodeReviewIntimeMoreInfo', 'Api\DashboardController@dashBoardCodeReviewIntimeMoreInfo')->name('dashboard-codereview-intime-more-info|Dashboard代码评审及时率详细信息');
        Route::post('dashboard/dashBoardDiffcountInfo', 'Api\DashboardController@dashBoardDiffcountInfo')->name('dashboard-diffcount-info|Dashboard代码修改量简短信息');
        Route::post('dashboard/dashBoardDiffcountMoreInfo', 'Api\DashboardController@dashBoardDiffcountMoreInfo')->name('dashboard-diffcount-more-info|Dashboard代码修改量详细信息');

        /* jenkins 页面接口 */
        Route::post('jenkins/projectLinkedList', 'Api\JenkinsController@projectLinkedList')->name('project-linked-list|已关联静态检查项目列表');
        Route::post('jenkins/createJenkinsJob', 'Api\JenkinsController@createJenkinsJob')->name('project-linked-list|已关联静态检查项目列表');
    });
});
