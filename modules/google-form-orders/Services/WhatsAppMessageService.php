<?php

namespace App\Services;

use App\Models\GoogleFormOrder;
use App\Models\GoogleFormOrderEvent;

class WhatsAppMessageService
{
    public function confirmationLink(GoogleFormOrderEvent $event, GoogleFormOrder $order): ?array
    {
        return $this->linkFor($event, $order, $event->confirmation_template);
    }

    public function updateLink(GoogleFormOrderEvent $event, GoogleFormOrder $order, string $updateMessage): ?array
    {
        return $this->linkFor($event, $order, $updateMessage);
    }

    private function linkFor(GoogleFormOrderEvent $event, GoogleFormOrder $order, string $template): ?array
    {
        // Prefer order-level WhatsApp number (edited per order), then customer phone.
        $rawPhone = trim((string) ($order->whatsapp_phone ?: $order->customer?->phone ?: ''));
        $phone = $this->normalizePhone($rawPhone !== '' ? $rawPhone : null);
        if (! $phone) {
            return null;
        }

        $text = $event->renderMessage($template, $order);

        return [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'name' => $order->customer?->name,
            'phone' => $phone,
            'phone_display' => $rawPhone,
            'from_phone' => $this->fromPhone(),
            'message' => $text,
            'url' => $this->url($phone, $text),
        ];
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        // 00… international prefix
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Local numbers starting with 0 → default country code (DE 49 / NL 31)
        if (str_starts_with($digits, '0')) {
            $cc = preg_replace('/\D+/', '', (string) config('foodeza.whatsapp_default_country_code', '49')) ?: '49';
            $digits = $cc.substr($digits, 1);
        }

        return $digits;
    }

    public function fromPhone(): ?string
    {
        return $this->normalizePhone(config('foodeza.whatsapp_from_phone') ?: config('foodeza.company_details.phone'));
    }

    public function url(string $phone, string $text): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
