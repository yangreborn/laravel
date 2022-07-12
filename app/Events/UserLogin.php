<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserLogin implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'mail_send_result';

    private $user_id;

    private $token;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        list(
            'user_id' => $this->user_id,
            'token' => $this->token,
        ) = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('User.' . $this->user_id);
    }

    public function broadcastWith()
    {
        return [
            'user_id' => $this->user_id,
            'token' => $this->token,
        ];
    }
}
