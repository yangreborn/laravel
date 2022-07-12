<?php

namespace App\Console\Commands;

use App\Models\Api;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class GetApiInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:api_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get all api info and store into database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        //
        $routes = Route::getRoutes()->getRoutes();
        foreach ($routes as $route){
            if (in_array('auth:api', $route->gatherMiddleware())){
                $name = $route->getName();
                $name = substr($name, strpos($name, '|') + 1);
                $uri = $route->uri();
                $item = [
                    'name' => $name,
                    'uri' => $uri,
                    'method' => json_encode($route->methods()),
                    'extra' => json_encode([
                        'middleware' => $route->getAction('middleware'),
                        'controller' => $route->getAction('controller'),
                        'namespace' => $route->getAction('namespace'),
                        'prefix' => $route->getAction('prefix'),
                    ]),
                ];
                Api::updateOrCreate(['uri' => $uri], $item);
            }
        }
        $this->info('同步接口信息完毕！');
    }
}
