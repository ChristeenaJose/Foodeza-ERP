<?php

use Foodeza\Bookkeeping\View;
?>
<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="subtitle">Catering · Food Truck · Corporate Lunch · Meal Box Delivery</p>
    </div>
    <form method="get" class="month-picker">
        <label for="month">Month</label>
        <input type="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card income">
        <span class="stat-label">Revenue</span>
        <span class="stat-value"><?= View::formatMoney($profit['revenue']) ?></span>
    </div>
    <div class="stat-card expense">
        <span class="stat-label">Expenses</span>
        <span class="stat-value"><?= View::formatMoney($profit['expenses']) ?></span>
    </div>
    <div class="stat-card net <?= $profit['net_profit'] >= 0 ? 'positive' : 'negative' ?>">
        <span class="stat-label">Net Profit</span>
        <span class="stat-value"><?= View::formatMoney($profit['net_profit']) ?></span>
    </div>
    <div class="stat-card asset">
        <span class="stat-label">VAT Due</span>
        <span class="stat-value"><?= View::formatMoney($profit['vat_due']) ?></span>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <h2>Revenue by Service</h2>
        <span class="muted"><?= htmlspecialchars($month) ?></span>
    </div>
    <div class="services-grid">
        <?php foreach ($serviceRevenue as $row): ?>
            <div class="service-card">
                <span class="service-name"><?= htmlspecialchars($row['service']) ?></span>
                <span class="service-amount"><?= View::formatMoney($row['total']) ?></span>
                <span class="service-meta"><?= (int) $row['count'] ?> entries</span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="grid-2">
    <section class="card">
        <div class="card-header">
            <h2>Outstanding Invoices</h2>
            <a href="/reports/outstanding" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <?php if (empty($outstanding)): ?>
            <p class="empty">No unpaid invoices.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Due</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($outstanding, 0, 5) as $inv): ?>
                        <tr>
                            <td><a href="/invoices/show?id=<?= (int) $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                            <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                            <td><?= htmlspecialchars($inv['due_date'] ?? '—') ?></td>
                            <td class="text-right amount-income"><?= View::formatMoney((float) $inv['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Recent Activity</h2>
        </div>
        <h3 class="section-label">Income</h3>
        <?php if (empty($recentIncome)): ?>
            <p class="empty-sm">No income yet.</p>
        <?php else: ?>
            <table class="table table-compact">
                <tbody>
                    <?php foreach ($recentIncome as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name'] ?? $row['service_type']) ?></td>
                            <td class="text-right amount-income"><?= View::formatMoney((float) $row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 class="section-label">Expenses</h3>
        <?php if (empty($recentExpenses)): ?>
            <p class="empty-sm">No expenses yet.</p>
        <?php else: ?>
            <table class="table table-compact">
                <tbody>
                    <?php foreach ($recentExpenses as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name'] ?? $row['category_name']) ?></td>
                            <td class="text-right amount-expense"><?= View::formatMoney((float) $row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<div class="quick-actions">
    <a href="/income/new" class="btn btn-primary">+ Record Income</a>
    <a href="/expenses/new" class="btn btn-secondary">+ Record Expense</a>
    <a href="/invoices/new" class="btn btn-secondary">+ Create Invoice</a>
</div>
