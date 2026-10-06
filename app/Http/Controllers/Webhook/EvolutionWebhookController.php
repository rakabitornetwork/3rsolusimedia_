<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Messaging\BotCommandRouter;
use App\Services\Messaging\MessagingManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookController extends Controller
{
    public function __construct(
        private readonly MessagingManager $channels,
        private readonly BotCommandRouter $commands,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $channel = $this->channels->driver('whatsapp');

        if (! $channel->verifyWebhook($request)) {
            return response('Forbidden', 403);
        }

        $message = $channel->parseIncoming($request);
        if (! $message) {
            return response('OK', 200);
        }

        try {
            $this->commands->handle($message);
        } catch (\Throwable $e) {
            Log::error('Evolution webhook failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }
}
