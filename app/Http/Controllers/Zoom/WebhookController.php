<?php

namespace App\Http\Controllers\Zoom;

use App\Http\Controllers\Controller;
use App\Jobs\HandleZoomWebhook;
use App\Zoom\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * POST /zoom/webhook. Must answer within 3 seconds, so it only verifies the
 * signature and queues the event. Also serves the CRC challenge.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $signature = WebhookSignature::fromConfig();

        if (! $signature->verify(
            $request->header('x-zm-request-timestamp'),
            $request->header('x-zm-signature'),
            $request->getContent(),
        )) {
            Log::warning('zoom.webhook.rejected', ['ip' => $request->ip(), 'event' => $request->input('event')]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = (string) $request->input('event', '');

        if ($event === 'endpoint.url_validation') {
            return response()->json($signature->challengeResponse((string) $request->input('payload.plainToken', '')));
        }

        HandleZoomWebhook::dispatch(
            $event,
            (array) $request->input('payload', []),
            (int) $request->input('event_ts', 0),
            (string) $request->header('x-zm-trackingid', ''),
        );

        return response()->noContent();
    }
}
