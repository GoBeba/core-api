<?php

namespace Fleetbase\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiaraSmsService
{
    protected ?string $apiKey;

    protected ?string $senderId;

    protected string $baseUrl;

    /**
     * Create a new TiaraSmsService instance.
     */
    public function __construct()
    {
        $this->apiKey   = config('services.tiara.api_key');
        $this->senderId = config('services.tiara.sender_id');
        $this->baseUrl  = config('services.tiara.api_endpoint', 'https://api.tiaraconnect.io/send-sms');

        Log::info('TiaraSmsService initialized', [
            'base_url'  => $this->baseUrl,
            'sender_id' => $this->senderId,
        ]);
    }

    /**
     * Send an SMS message.
     *
     * @param string      $to       Recipient phone number
     * @param string      $text     Message text
     * @param string|null $senderId Optional sender ID
     *
     * @return array Response containing status and message ID
     *
     * @throws \Exception If API request fails
     */
    public function send(string $to, string $text, ?string $senderId = null): array
    {
        $senderId = $senderId ?? $this->senderId;

        // Tiara OMS v2 usually expects numbers without the + prefix
        $to = str_replace('+', '', $to);

        try {
            Log::info('Sending SMS via Tiara', [
                'to'   => $to,
                'from' => $senderId,
                'text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->post($this->baseUrl, [
                'to'      => $to,
                'message' => $text,
                'from'    => $senderId,
            ]);

            $statusCode = $response->status();
            $body       = $response->json();

            if ($response->successful()) {
                Log::info('Tiara SMS sent successfully', [
                    'message_id' => $body['message_id'] ?? null,
                ]);

                return [
                    'success'    => true,
                    'message_id' => $body['message_id'] ?? null,
                    'result'     => 'SUCCESS',
                ];
            }

            Log::error('Tiara SMS sending failed', [
                'status_code' => $statusCode,
                'response'    => $body,
            ]);

            return [
                'success' => false,
                'error'   => $body['message'] ?? 'API request failed',
                'code'    => $statusCode,
            ];
        } catch (\Throwable $e) {
            Log::error('Tiara SMS API request failed', [
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to send SMS via Tiara: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }
}
