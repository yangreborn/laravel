<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Events\AccessTokenCreated;

class TokenCreateListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public $connection = 'database';

    public $tries = 1;

    /**
     * Handle the event.
     *
     * @param AccessTokenCreated $event
     * @return void
     */
    public function handle(AccessTokenCreated $event)
    {
        $useless_tokens = DB::table('oauth_access_tokens')
            ->where('user_id', $event->userId)
            ->where('id', '<>', $event->tokenId)
            ->where('client_id', $event->clientId)
            ->get();

        foreach ($useless_tokens as $useless_token) {
            DB::table('oauth_refresh_tokens')->where('access_token_id', $useless_token->id)->delete();
            DB::table('oauth_access_tokens')->where('id', $useless_token->id)->delete();
        }
    }
}
