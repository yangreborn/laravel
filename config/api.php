<?php

return [
    'default_password' => 'yfzlb',
    'page_size' => 15,
    'password_expired' => 365,
    // 用户类型
    'user_type' => [
        0 => 'guest',
        1 => 'admin',
        2 => 'sqa',
        3 => 'user',
    ],
    // 项目指标,14项
    'project_index' => [
        'design_doc_finish_rate' => [
            'label' => '设计文档齐套达标率',//设计文档齐套率
            'value' => [90, 100], // 期望值
            'manual' => true, // 是否人工写入：true，人工手写；false，后台代码统计
            'stage' => 1, // 指标所属阶段、
            'classification' => [0],  // 指标项目类型、
            'type' => 1, // 指标类型：1、百分比；2、数值型
            'min' => 20, // 滑条最小值
            'max' => 100, // 滑条最大值
            'up' => [ // 比率分子信息
                'key' => 'design_doc_actual_count',
                'label' => '实际输出设计文档数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'design_doc_planned_count',
                'label' => '计划输出设计文档数',
            ],
            'tool' => [], // 对应工具
        ],
        'design_doc_review_coverage_rate' => [
            'label' => '设计文档评审覆盖达标率',//设计文档评审覆盖率
            'value' => [90, 100],
            'manual' => true,
            'stage' => 1,
            'classification' => [0, 1],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'design_doc_review_count',
                'label' => '设计文档评审数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'design_doc_actual_count',
                'label' => '实际输出设计文档数',
            ],
            'tool' => [], // 对应工具
        ],
        'design_doc_review_debug_rate' => [
            'label' => '设计文档评审缺陷解决达标率',//设计文档评审缺陷解决率
            'value' => [100, 100],
            'manual' => true,
            'stage' => 1,
            'classification' => [0, 1],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'design_doc_review_debug_count',
                'label' => '已解决设计文档评审缺陷数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'design_doc_review_bug_count',
                'label' => '设计文档评审缺陷总数',
            ],
            'tool' => [], // 对应工具
        ],
        'static_check_serious_bug_count' => [
            'label' => '静态检查严重缺陷遗留数达标率',//静态检查严重缺陷遗留数
            'value' => [0, 10],
            'manual' => false,
            'stage' => 2,
            'classification' => [0, 1, 2],
            'type' => 2,
            'min' => 0,
            'max' => 20,
            'tool' => ['pclint', 'tscancode', 'eslint', 'findbugs'], // 对应工具
        ],
        'code_annotation_rate' => [
            'label' => '代码注释达标率',//代码注释率
            'value' => [18, 100],
            'manual' => false,
            'stage' => 2,
            'classification' => [0, 1, 2],
            'type' => 1,
            'min' => 18,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'code_comment_lines',
                'label' => '代码注释行数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'code_lines',
                'label' => '有效代码行数',
            ],
            'tool' => ['cloc'], // 对应工具
        ],
        'review_time_per_capita_count' => [ //原:代码评审覆盖率-线下
            'label' => '人均评审时长-线下达标率',//人均评审时长-线下
            'value' => [0.75, 1],
            'manual' => true,
            'stage' => 2,
            'classification' => [0, 2],
            'type' => 2,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'review_time_multiply_reviewers_numbers',
                'label' => '评审时长*开发人员评审人数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'departments_numbers',
                'label' => '部门人数（度量平台上人数）',
            ],
            'tool' => [], // 对应工具
        ],
        'code_online_review_coverage_rate' => [
            'label' => '代码评审覆盖率-线上达标率',//代码评审覆盖率-线上
            'value' => [100, 100],
            'manual' => false,
            'stage' => 2,
            'classification' => [0, 1, 2],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'code_online_review_times',
                'label' => '代码评审次数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'code_commit_times',
                'label' => '代码提交次数',
            ],
            'tool' => ['phabricator', 'gerrit'], // 对应工具
        ],
        'code_online_review_efficiency_rate' => [ //add
            'label' => '评审有效率-线上达标率',//评审有效率-线上
            'value' => [60, 100],
            'manual' => false,
            'stage' => 2,
            'classification' => [0, 1, 2],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'code_online_effective_review_times',
                'label' => '代码有效评审次数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'code_online_review_times',
                'label' => '代码评审次数',
            ],
            'tool' => ['phabricator', 'gerrit'], // 对应工具
        ],
        'code_online_review_timely_rate' => [ //add
            'label' => '评审及时率-线上达标率',//评审及时率-线上
            'value' => [90, 100],
            'manual' => false,
            'stage' => 2,
            'classification' => [0, 1, 2],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'code_online_timely_review_times',
                'label' => '代码及时评审次数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'code_online_review_times',
                'label' => '代码评审次数',
            ],
            'tool' => ['phabricator', 'gerrit'], // 对应工具
        ],
        'test_case_review_coverage_rate' => [
            'label' => '系统测试用例评审覆盖达标率',//系统测试用例评审覆盖率
            'value' => [90, 100],
            'manual' => true,
            'stage' => 1,
            'classification' => [0, 1],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'test_case_review_count',
                'label' => '系统测试用例评审数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'test_case_count',
                'label' => '实际输出系统测试用例数',
            ],
            'tool' => [], // 对应工具
        ],
        'issue_static_check_serious_bug_count' => [
            'label' => '发布遗留静态检查严重缺陷数达标率',//发布遗留静态检查严重缺陷数
            'value' => [0, 10],
            'manual' => false,
            'stage' => 4,
            'classification' => [0],
            'type' => 2,
            'min' => 0,
            'max' => 20,
            'tool' => ['pclint', 'tscancode', 'eslint', 'findbugs'], // 对应工具
        ],
        'issue_code_review_coverage_online_rate' => [
            'label' => '发布代码线上评审覆盖达标率',//发布代码线上评审覆盖率
            'value' => [80, 100],
            'manual' => false,
            'stage' => 4,
            'classification' => [0],
            'type' => 1,
            'min' => 20,
            'max' => 100,
            'up' => [ // 比率分子信息
                'key' => 'issue_code_review_times',
                'label' => '代码提交经评审次数',
            ],
            'down' => [ // 比率分母信息
                'key' => 'issue_code_commit_times',
                'label' => '代码提交次数',
            ],
            'tool' => ['phabricator', 'gerrit'], // 对应工具
        ],
        'issue_bug_count' => [
            'label' => '发布遗留测试缺陷数达标率',//发布遗留测试缺陷数
            'value' => [0, 10],
            'manual' => true,
            'stage' => 4,
            'classification' => [0],
            'type' => 2,
            'min' => 0,
            'max' => 20,
            'tool' => [], // 对应工具
        ],
        'issue_serious_bug_count' => [
            'label' => '发布遗留测试严重缺陷数达标率',//发布遗留测试严重缺陷数
            'value' => [0, 0],
            'manual' => true,
            'stage' => 4,
            'classification' => [0],
            'type' => 2,
            'min' => 0,
            'max' => 20,
            'tool' => [], // 对应工具
        ],
    ],
    // 项目阶段
    'project_stage' => [
        'prepare_stage' => [
            'label' => '准备阶段',
            'value' => 0,
        ],
        'design_stage' => [
            'label' => '设计阶段',
            'value' => 1,
        ],
        'develop_stage' => [
            'label' => '开发阶段',
            'value' => 2,
        ],
        'test_stage' => [
            'label' => '测试阶段',
            'value' => 3,
        ],
        'publish_stage' => [
            'label' => '发布阶段',
            'value' => 4,
        ],
        'finish' => [
            'label' => '项目结束',
            'value' => 5,
        ],
    ],
    // 项目类型
    'project_classification' => [
        'basic_product' => [
            'label' => '基础产品',
            'value' => 0,
        ],
        'solution' => [
            'label' => '解决方案',
            'value' => 1,
        ],
        'resource_sector' => [
            'label' => '资源部门',
            'value' => 2,
        ],
    ],
    // 财季区间
    'fiscal_season' => [
        ['03-26', '06-25'], // 一季度
        ['06-26', '09-25'], // 二季度
        ['09-26', '12-25'], // 三季度
        ['12-26', '03-25'] // 四季度
    ],
    'season' => [
        [4, 5, 6], // 一季度
        [7, 8, 9], // 二季度
        [10, 11, 12], // 三季度
        [1, 2, 3] // 四季度
    ],
    // 工具
    'tools' => [
        [
            'title' => '静态检查工具',
            'children' => [
                ['shortname' => 'pclint', 'name' => 'PC-Lint'],
                ['shortname' => 'tscancode', 'name' => 'Tscan Code'],
                ['shortname' => 'diffcount', 'name' => 'Diffcount'],
                ['shortname' => 'findbugs', 'name' => 'Findbugs'],
                ['shortname' => 'eslint', 'name' => 'ESLint'],
                ['shortname' => 'compile', 'name' => 'Compile'],
                ['shortname' => 'cloc', 'name' => 'Cloc'],
            ]
        ],
        [
            'title' => '代码评审工具',
            'children' => [
                ['shortname' => 'phabricator', 'name' => 'Phabricator'],
                ['shortname' => 'gerrit', 'name' => 'Gerrit'],
            ]
        ],
        [
            'title' => '缺陷管理工具',
            'children' => [
                ['shortname' => 'plm', 'name' => 'Plm'],
                ['shortname' => 'tapd', 'name' => 'Tapd'],
            ]
        ],
    ],
    // phabricator提交人动作
    'phabricator_submitter_action' => [
        'create',
        'update',
        'commit',
        'abandon',
        'request_review',
        'reclaim',
        'rethink',
        'submit_directly', // 未作任何评审
    ],
    // plm系统bug状态
    'plm_bug_status' => [
        [
            'label' => '待解决',
            'value' => 'to_be_solved',
        ],
        [
            'label' => '延期',
            'value' => 'delayed',
        ],
        [
            'label' => '待验证',
            'value' => 'validated',
        ],
        [
            'label' => '关闭',
            'value' => 'closed',
        ],
    ],
    // plm报告组成部分
    'plm_report_parts' => [
        ['label' => '总况信息', 'value' => 'part0'],
        ['label' => '按严重性统计', 'value' => 'part1'],
        ['label' => '按产品名统计', 'value' => 'part2'],
        ['label' => '按审阅者统计', 'value' => 'part3'],
        ['label' => '按测试人员统计', 'value' => 'part4'],
        ['label' => '延期信息', 'value' => 'part5'],
        ['label' => '分组趋势变化', 'value' => 'part6'],
        ['label' => '按分管小组统计', 'value' => 'part7'],
        ['label' => '被拒绝信息展示', 'value' => 'part8'],
        ['label' => '按关闭方式统计', 'value' => 'part9'],
        ['label' => '缺失审阅者信息统计', 'value' => 'part10'],
    ],
    // plm报告中总况表中展示主体（项目名或产品名）
    'plm_show_names' => [
        ['label' => '按项目名称', 'value' => 'project'],
        ['label' => '按产品名称', 'value' => 'product'],
    ],

    //tapd bug状态
    'tapd_bug_status' =>[
        [
            'label' => '新',
            'value' => '新',
        ],
        [
            'label' => '新建',
            'value' => '新建',
        ],
        [
            'label' => 'Submitted',
            'value' => 'Submitted',
        ],
        [
            'label' => 'Submitted/新建',
            'value' => 'Submitted/新建',
        ],
        [
            'label' => 'Submitted/提交',
            'value' => 'Submitted/提交',
        ],
        [
            'label' => 'Opened',
            'value' => 'Opened',
        ],
        [
            'label' => 'Opened/研LTM转研发',
            'value' => 'Opened/研LTM转研发',
        ],
        [
            'label' => 'Opened/研发LTM打开给对应开发',
            'value' => 'Opened/研发LTM打开给对应开发',
        ],
        [
            'label' => '分配',
            'value' => '分配',
        ],
        [
            'label' => '分配-开发LTM',
            'value' => '分配-开发LTM',
        ],
        [
            'label' => 'Assigned',
            'value' => 'Assigned',
        ],
        [
            'label' => 'Assign',
            'value' => 'Assign',
        ],
        [
            'label' => 'Assigned/测LTM转研LTM',
            'value' => 'Assigned/测LTM转研LTM',
        ],
        [
            'label' => 'Assigned/指派',
            'value' => 'Assigned/指派',
        ],
        [
            'label' => '接收/处理',
            'value' => '接收/处理',
        ],
        [
            'label' => '接受/处理',
            'value' => '接受/处理',
        ],
        [
            'label' => '接受/处理-开发人员',
            'value' => '接受/处理-开发人员',
        ],
        [
            'label' => '审核',
            'value' => '审核',
        ],
        [
            'label' => '审核-测试LTM',
            'value' => '审核-测试LTM',
        ],
        [
            'label' => 'Verified',
            'value' => 'Verified',
        ],
        [
            'label' => '已解决',
            'value' => '已解决',
        ],
        [
            'label' => 'Resolved',
            'value' => 'Resolved',
        ],
        [
            'label' => 'Resolved/已解决',
            'value' => 'Resolved/已解决',
        ],
        [
            'label' => '重新打开',
            'value' => '重新打开',
        ],
        [
            'label' => '已验证',
            'value' => '已验证',
        ],
        [
            'label' => '已拒绝',
            'value' => '已拒绝',
        ],
        [
            'label' => 'Transfer',
            'value' => 'Transfer',
        ],
        [
            'label' => 'Suspended',
            'value' => 'Suspended',
        ],
    ],

    'tapd_task_status' =>[
        [
            'label' => '未开始',
            'value' => 'open',
        ],
        [
            'label' => '进行中',
            'value' => 'progressing',
        ],
        [
            'label' => '已完成',
            'value' => 'done',
        ],
    ],

    // tscan error type
    'ignore_type' => [
        [
            'label' => 'All Type',
            'value' => 'All Type',
        ],
        [
            'label' => 'nullpointer',
            'value' => 'nullpointer',
        ],
        [
            'label' => 'memleak',
            'value' => 'memleak',
        ],
        [
            'label' => 'bufoverrun',
            'value' => 'bufoverrun',
        ],
        [
            'label' => 'compute',
            'value' => 'compute',
        ],
        [
            'label' => 'logic',
            'value' => 'logic',
        ],
        [
            'label' => 'suspicious',
            'value' => 'suspicious',
        ],
    ],

    //pclint禁用类型
    'ignore_sign' => [
        [
            'label' => '#',
            'value' => '#',
        ],
        [
            'label' => 'REM',
            'value' => 'REM',
        ],
    ],

    // chao_emails
    'chao_emails' => ["yanjunjie@kedacom.com", "caohaizhang@kedacom.com"],

    // 邮件标题
    'subject' => [
        'plm_bug_process_report' => '一周内未解决（验证）Bug统计报告',
        'plm_all_bug_process_report' => '一周内未处理Bug统计报告',
        'plm_unrecognized_bug_notification' => '未能识别Plm缺陷相关责任人的通知',

        'pclint' => 'C/C++ 静态检查PC-Lint项目周报',
        'tscan' => 'C/C++ 静态检查TscanCode项目周报',
        'plm' => '缺陷度量plm报告',
        'codereview' => '代码评审报告',
        'diffcount' => '代码提交统计diffcount报告',
        'tapd_bug_process_report' => '一周内未解决（验证）Bug统计报告',
        'sync_project_tool_data_notification' => '项目与流自动关联结果',
        'tapd_plm_not_updated_all' => '【请阅】TAPD&PLM在研项目缺陷更新信息通知',
        'tapd_plm_not_updated_single' => '【请阅】TAPD&PLM在研项目缺陷更新信息提醒',
        'tapd_notification' => '外部项目需求&缺陷统计报告',
        'tapd_will_over_due_task' => '【任务提醒】TAPD 智能运维相关项目即将到期任务通知',
    ],
    'other_dev_email' => 'caohaizhang@kedacom.com',
    'dev_email' => 'liwei_cxzyzx@kedacom.com',
    'test_email' => 'ggcs@kedacom.com',

    // plm报告top3字段
    'plm_top_three_fields' => [
        'unresolve_bug_group' => [
            'title' => '待解决Bug数按负责小组排序Top3',
        ],
        'unresolve_bug_reviewer' => [
            'title' => '待解决Bug数按当前审阅者排序Top3',
        ],
        'unresolve_bug_project' => [
            'title' => '待解决Bug数按所属项目排序Top3',
        ],
        'new_bug_group' => [
            'title' => '新增Bug数按负责小组排序Top3',
        ],
        'resolve_bug_group' => [
            'title' => '解决Bug数按负责小组排序Top3',
        ],
        'unresolve_bug_reject_group' => [
            'title' => '待解决Bug被拒绝次数按负责小组排序Top3',
        ],
        'new_bug_creator' => [
            'title' => '本次统计新建Bug数按测试人员排序Top3',
        ],
        'validate_bug_reviewer' => [
            'title' => '待验证Bug数按审阅者排序Top3',
        ],
    ],
    // Plm Bug 严重程度颜色
    'plm_bug_color' => [
        '致命' => ["R"=>168,"G"=>7,"B"=>26,"Alpha"=>100], // 致命
        '严重' => ["R"=>250,"G"=>84,"B"=>28,"Alpha"=>100], // 严重
        '普通' => ["R"=>255,"G"=>169,"B"=>64,"Alpha"=>100], // 普通
        '较低' => ["R"=>255,"G"=>245,"B"=>102,"Alpha"=>100], // 较低
        '建议' => ["R"=>64,"G"=>169,"B"=>255,"Alpha"=>100], // 建议
    ],
    // Tapd Bug状态（原始状态，注意与自定义状态区分）与时间对应关系
    'tapd_status_time' => [
        'in_progress' => 'in_progress_time',
        'resolved' => 'resolved',
        'verified' => 'verify_time',
        'closed' => 'closed',
        'reopened' => 'reopen_time',
        'TM_audited' => 'audit_time',
        'PMM_audited' => 'audit_time',
        'PM_audited' => 'audit_time',
        'QA_audited' => 'audit_time',
        'suspended' => 'suspend_time',
    ],
    // Tapd用户字段对应
    'tapd_user_fields' => [
        ['id' => 0, 'label' => '处理人', 'value' => 'current_owner'], // 默认
    ],
    // Tapd映射状态
    'tapd_mapping_status' => [
        ['id' => 0, 'label' => 'Assigned', 'value' => 'assigned'], //已指派/待解决
        ['id' => 1, 'label' => 'Accessed', 'value' => 'accessed'], //已接受/待解决
        ['id' => 2, 'label' => 'Resolved', 'value' => 'resolved'], //已解决
        ['id' => 3, 'label' => 'Closed', 'value' => 'closed'], //已关闭
    ],
    // Tapd缺陷优先级映射
    'tapd_mapping_priority' => [
        'urgent' => '紧急',
        'high' => '高',
        'medium' => '中',
        'low' => '低',
        'insignificant' => '无关紧要',
    ],
    // Tapd缺陷严重性映射
    'tapd_mapping_severity' => [
        'fatal' => '致命',
        'serious' => '严重',
        'normal' => '一般',
        'prompt' => '提示',
        'advice' => '建议',
    ],
    // 需同步数据的Ldap部门（一级部门）：名称=>cn值
    'sync_ldap_departments' => [
        '事业部群' => '0130',
        '监控产品线' => '0102',
        '视讯产品线' => '0105',
        '创新资源中心' => '0104',
        '营销中心' => '0110',
    ],
    // 特殊用户
    'special_user_email' => [
        'admin', // 管理员
        'bot', // 机器人
    ],
    // jenkins api json
    'jenkins_api' => [
        'job_list' =>   [
            'http://172.16.1.147:8080/api/json',
            'http://172.16.1.153:8080/api/json',
            'http://172.16.2.146:8080/api/json',
            'http://172.16.2.147:8080/api/json',
            'http://172.16.0.209:8080/api/json',
            'http://172.16.1.148:8080/api/json',
            'http://172.16.1.171:8080/api/json',
            'http://172.16.1.155:8080/api/json',
        ],
    ],
    'regular_meeting' => [
        'holiday' => [
            '2021-10-01', '2021-10-02', '2021-10-03', '2021-10-04', '2021-10-05', '2021-10-06', '2021-10-07', '2021-10-08', 
            '2021-02-11', '2021-02-12', '2021-02-13', '2021-02-14', '2021-02-15', '2021-02-16', '2021-02-17', '2021-03-05',
            '2021-05-03', '2021-05-04', '2021-05-05', '2021-06-14', '2021-09-20', '2021-09-21', 
        ],
        'meeting_room' => [
            'Shanghai' => '上海2007监控4A',
            'Suzhou' => '苏州五号楼5-7J',
        ],
        'odrer' => [
            1 => 'zhangping@kedacom.com',
            2 => 'zuoronghua@kedacom.com',
            3 => 'liuxiaolin@kedacom.com',
            4 => 'liufeng@kedacom.com',
            5 => 'zuolin@kedacom.com',
            6 => 'yanjunjie@kedacom.com',
            7 => 'caohaizhang@kedacom.com',
            8 => 'liwei_cxzyzx@kedacom.com',
            9 => 'yangjiawei@kedacom.com',
        ],
    ],
    'over_due_task_project' => [
        47319053,
        61554994,
    ],

    // tapd报告组成部分
    'tapd_report_parts' => [
        ['label' => '总况信息', 'value' => 'part0'],
        ['label' => '按严重性统计', 'value' => 'part1'],
        ['label' => '按处理者统计', 'value' => 'part2'],
        ['label' => '延期信息', 'value' => 'part3'],
        ['label' => '超期信息', 'value' => 'part4'],
    ],

    // 外网映射地址
    'external_url' => 'http://180.168.251.199:88',

    // 部门sqa对应关系
    'department_sqa' => [
        '视讯产品线视讯云平台产品部' => '刘晓林',
        '视讯产品线视讯云平台融合部' => '刘晓林',
        '视讯产品线网呈产品部' => '刘晓林',
        '终端产品部终端架构部' => '刘晓林',
        '终端产品部终端业务组件部' => '刘晓林',
        '终端产品部硬终端产品部' => '刘晓林',
        '终端产品部软终端产品部' => '刘晓林',
        '终端产品部产品管理组' => '刘晓林',
        '终端产品部终端系统工程部' => '刘晓林',
        '终端产品部终端测试部' => '刘晓林',
        '终端产品部终端媒体技术部' => '刘晓林',
        '终端产品部产品经理部' => '刘晓林',
        '终端SKYOS开发部' => '刘晓林',
        '视讯产品线视讯云平台资源部' => '刘晓林',
        '视讯产品线智能云技术部' => '刘晓林',
        '视讯产品线系统架构与组件部' => '刘晓林',
        '视讯产品线媒体网络部' => '刘晓林',

        '终端产品部摄像机产品部' => '张晓艳',
        '监控产品线固定IPC产品部' => '张晓艳',
        '监控产品线云台IPC产品部' => '张晓艳',
        '移动事业部无线产品部' => '张晓艳',
        '音视系统事业部' => '张晓艳',
        '创新资源中心Web前端技术部' => '张晓艳',
        '创新资源中心公共应用技术部' => '张晓艳',
        '创新资源中心移动终端软件部' => '张晓艳',

        '监控产品线边端平台部' => '刘丰',
        '监控产品线NVR产品部' => '刘丰',
        '研发二部' => '刘丰',
        '研发六部' => '刘丰',
        '研发九部' => '刘丰',
        
        '监控产品线云平台产品部' => '张平',
        '移动事业部移动平台开发部' => '张平',

        '智慧城市事业部' => '左琳',
        '政法产品部' => '左琳',
        '研发三部' => '左琳',
        '创新资源中心软件技术中心' => '左琳',

        '研发一部' => '左荣华',
        '研发四部' => '左荣华',
        '研发五部' => '左荣华',
        '研发七部' => '左荣华',
        '研发八部' => '左荣华',
    ],
    
];
