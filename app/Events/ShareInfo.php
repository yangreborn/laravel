<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

class ShareInfo
{
    use SerializesModels;


    private $model;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    public function getModal(){
        return $this->model;
    }
}
