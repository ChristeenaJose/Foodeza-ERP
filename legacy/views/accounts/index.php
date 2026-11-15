<?php

use Foodeza\Bookkeeping\View;

$typeLabels = [
    'asset' => 'Assets',
    'liability' => 'Liabilities',
    'income' => 'Income Accounts',
    'expense' => 'Expense Accounts',
];
?>
<div class="page-header">
    <div>
        <h1>Chart of Accounts</h1>
        <p class="subtitle">Manage your business accounts</p>
    </div>
    <a href="/accounts/new" class="btn btn-primary">+ New Account</a>
</div>

<div class="stats-grid stats-grid-sm">
    <?php foreach ($totals as $type => $total): ?>
        <div class="stat-card mini">
            <span class="stat-label"><?= $typeLabels[$type] ?? ucfirst($type) ?></span>
            <span class="stat-value"><?= View::formatMoney($total) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Description</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($account['name']) ?></strong></td>
                    <td><span class="badge badge-<?= $account['type'] ?>"><?= ucfirst($account['type']) ?></span></td>
                    <td class="muted"><?= htmlspecialchars($account['description'] ?? '—') ?></td>
                    <td class="text-right"><?= View::formatMoney((float) $account['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
