<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/12/20
 * Time: 10:58
 */
namespace App\Http\Controllers\Traits;

use GuzzleHttp\Client;
use App\Exceptions\ApiException;
use GuzzleHttp\Exception\RequestException;

trait ProxyHelpers
{
    /**
     * 授权方法
     * @return mixed
     * @throws ApiException
     */
    public function authenticate()
    {
        $client = new Client([
            'base_uri' => request()->root(),
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        ]);

        try {
            $url = '/api/oauth/token';

            $params = array_merge(config('passport.proxy'), [
                'username' => request('email'),
                'password' => request('password'),
            ]);

            $respond = $client->request('POST', $url, [
                'form_params' => $params,
                'headers' => [
                    'Referer' => request()->root() . $url,
                    ],
                ]);
        } catch (RequestException $exception) {
            throw new ApiException('账号('. request('email') .')或密码错误');
        }

        if ($respond->getStatusCode() !== 401) {
            return json_decode($respond->getBody()->getContents(), true);
        }

        throw new ApiException('账号('. request('email') .')或密码错误');
    }
}