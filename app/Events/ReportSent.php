<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReportSent
{
    use SerializesModels;

    private $mail;
    private $user_id;
    private $tool;

    /**
     * Create a new event instance.
     *
     * @param mixed $args
     * @return void
     */
    public function __construct(...$args)
    {
        list($this->mail, $this->user_id, $this->tool) = $args;
    }

    public function getMail(){
        return $this->mail;
    }

    public function getUserId(){
        return $this->user_id;
    }

    public function getTool(){
        return $this->tool;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
//    public function broadcastOn()
//    {
//        return new PrivateChannel('channel-name');
//    }
}
