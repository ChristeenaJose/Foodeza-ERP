<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Payments</h1>
        <p class="subtitle">Cash, bank transfer, card, SumUp, Stripe</p>
    </div>
    <a href="/payments/new" class="btn btn-primary">+ Record Payment</a>
</div>

<section class="card">
    <form method="get" class="filters">
        <div class="form-group">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="form-group">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <div class="stats-grid stats-grid-sm">
        <?php foreach ($totalsByMethod as $row): ?>
            <div class="stat-card mini">
                <span class="stat-label"><?= htmlspecialchars(View::paymentMethod($row['payment_method'])) ?></span>
                <span class="stat-value"><?= View::formatMoney((float) $row['total']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Method</th>
                <th>Invoice</th>
                <th>Reference</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['date']) ?></td>
                    <td><?= htmlspecialchars(View::paymentMethod($p['payment_method'])) ?></td>
                    <td><?= htmlspecialchars($p['invoice_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['reference'] ?? '—') ?></td>
                    <td class="text-right amount-income"><?= View::formatMoney((float) $p['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
