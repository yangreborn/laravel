<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/12/20
 * Time: 11:00
 */

return [
    'proxy' => [
        'grant_type'    => env('OAUTH_GRANT_TYPE'),
        'client_id'     => env('OAUTH_CLIENT_ID'),
        'client_secret' => env('OAUTH_CLIENT_SECRET'),
        'scope'         => env('OAUTH_SCOPE', '*'),
    ],
];
