<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MailSendResult implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'mail_send_result';

    private $user_id;

    private $subject;

    private $description;

    // 消息类型， success, error
    private $type;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        list(
            'user_id' => $this->user_id,
            'subject' => $this->subject,
            'description' => $this->description,
            'type' => $this->type,
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
        $type_text = $this->type === 'success' ? '成功' : '失败';
        $description = $this->type === 'success' ? '' : $this->description;
        return [
            'user_id' => $this->user_id,
            'message' => '《' . $this->subject . '》邮件发送' . $type_text,
            'description' => $description,
            'type' => $this->type,
        ];
    }
}
