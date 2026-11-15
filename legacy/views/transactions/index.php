<?php

use Foodeza\Bookkeeping\View;
?>
<div class="page-header">
    <div>
        <h1>Transactions</h1>
        <p class="subtitle">All income and expense entries</p>
    </div>
    <a href="/transactions/new" class="btn btn-primary">+ New Transaction</a>
</div>

<section class="card">
    <form method="get" class="filters">
        <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All</option>
                <option value="income" <?= ($filters['type'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                <option value="expense" <?= ($filters['type'] ?? '') === 'expense' ? 'selected' : '' ?>>Expense</option>
            </select>
        </div>
        <div class="form-group">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="/transactions" class="btn btn-ghost">Clear</a>
    </form>

    <?php if (empty($transactions)): ?>
        <p class="empty">No transactions found.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Account</th>
                        <th>Service</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td><?= htmlspecialchars($tx['date']) ?></td>
                        <td>
                            <?= htmlspecialchars($tx['description']) ?>
                            <?php if ($tx['reference']): ?>
                                <small class="muted">Ref: <?= htmlspecialchars($tx['reference']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($tx['account_name']) ?></td>
                        <td><?= htmlspecialchars($tx['category_name'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
                        <td class="text-right amount-<?= $tx['type'] ?>"><?= View::formatMoney((float) $tx['amount']) ?></td>
                        <td>
                            <form method="post" action="/transactions/delete" class="inline-form" onsubmit="return confirm('Delete this transaction?')">
                                <input type="hidden" name="id" value="<?= (int) $tx['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
