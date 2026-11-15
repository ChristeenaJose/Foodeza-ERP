<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        $config = require __DIR__ . '/../config/app.php';
        extract($data);
        $config = $config;

        ob_start();
        require __DIR__ . '/../views/' . $template . '.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layout.php';
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function formatMoney(float $amount): string
    {
        $config = require __DIR__ . '/../config/app.php';
        $symbol = $config['currency_symbol'];

        return $symbol . number_format($amount, 2, '.', ',');
    }

    public static function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    /** @return array{message: string, type: string}|null */
    public static function getFlash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }

    public static function paymentMethod(string $key): string
    {
        $config = require __DIR__ . '/../config/app.php';

        return $config['payment_methods'][$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'paid' => 'badge-income',
            'partial' => 'badge-asset',
            default => 'badge-expense',
        };
    }
}
