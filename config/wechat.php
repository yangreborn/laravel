<?php
    return [
        // wechat robot key
        'wechat_robot_key' => [
            'elk' => env('WECHAT_ELK_ROBOT'),
            'mpl_log' => env('WECHAT_MPL_LOG_ROBOT'),
            'regular_meeting' => env('WECHAT_REGULAR_MEETING_ROBOT'),
            'svnurl_remind'=> env('WECHAT_SVNURL_REMIND_ROBOT'),
        ],
        // 企业微信公司ID
        'agent_id' => env('WECHAT_AGENT_ID'),
        // 企业微信公司PID
        'company_pid' => env('WECHAT_COMPANY_PID'),
        // 软件质量应用密钥
        'app_sqa_secret' => env('WECHAT_APP_SQA_SECERT'),

        // 软件质量应用回调
        'callback_token' => env('WECHAT_APP_CALLBACK_TOKEN'),
        'encoding_aes_key' => env('WECHAT_APP_CALLBACK_ENCODINGAESKEY'),
        'dev' => ['KD010234', 'KD013625'],
    ];