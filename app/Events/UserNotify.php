<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

class UserNotify
{
    use SerializesModels;

    private $data;

    /**
     * Create a new event instance.
     *
     * @param mixed $args
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function __get($name) {
        return $this->data[$name];
    }

    public function __isset($name) {
        return key_exists($name, $this->data);
    }
}
