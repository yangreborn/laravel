<?php

return [
    // Tapd需求优先级映射
    'story_mapping_priority' => [
        '4' => '4_High',
        '3' => '3_Middle',
        '2' => '2_Low',
        '1' => '1_Nice To Have',
    ],
    // Tapd缺陷严重性映射
    'bug_mapping_severity' => [
        'fatal' => '5_致命',
        'serious' => '4_严重',
        'normal' => '3_一般',
        'prompt' => '2_提示',
        'advice' => '1_建议',
    ],
];