<?php

declare(strict_types=1);

use Foodeza\Bookkeeping\View;

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$flash = View::getFlash();

$navItems = [
    '/' => 'Dashboard',
    '/income' => 'Income',
    '/expenses' => 'Expenses',
    '/invoices' => 'Invoices',
    '/payments' => 'Payments',
    '/bank' => 'Bank',
    '/reports/profit-loss' => 'Reports',
    '/reports/attachments' => 'Receipts',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-icon">F</span>
                <div>
                    <strong><?= htmlspecialchars($config['company']) ?></strong>
                    <small>Bookkeeping</small>
                </div>
            </div>
            <nav>
                <?php foreach ($navItems as $path => $label): ?>
                    <?php
                    $active = $currentPath === $path
                        || ($path === '/reports/profit-loss' && str_starts_with($currentPath, '/reports'));
                    ?>
                    <a href="<?= $path ?>" class="<?= $active ? 'active' : '' ?>">
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="main">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</body>
</html>
