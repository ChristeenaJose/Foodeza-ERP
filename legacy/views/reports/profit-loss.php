<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Reports</h1>
        <p class="subtitle">Profit & loss, revenue, expenses</p>
    </div>
    <div class="btn-group">
        <a href="/reports/vat" class="btn btn-secondary">VAT Report</a>
        <a href="/reports/outstanding" class="btn btn-secondary">Outstanding</a>
    </div>
</div>

<section class="card">
    <form method="get" class="filters">
        <div class="form-group">
            <label for="month">Month</label>
            <input type="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>">
        </div>
        <div class="form-group">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="form-group">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Generate</button>
    </form>
</section>

<div class="stats-grid">
    <div class="stat-card income">
        <span class="stat-label">Total Revenue</span>
        <span class="stat-value"><?= View::formatMoney($profit['revenue']) ?></span>
    </div>
    <div class="stat-card expense">
        <span class="stat-label">Total Expenses</span>
        <span class="stat-value"><?= View::formatMoney($profit['expenses']) ?></span>
    </div>
    <div class="stat-card <?= $profit['net_profit'] >= 0 ? 'positive' : 'negative' ?>">
        <span class="stat-label">Net Profit</span>
        <span class="stat-value"><?= View::formatMoney($profit['net_profit']) ?></span>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Revenue by Service</h2>
        <table class="table">
            <thead><tr><th>Service</th><th class="text-right">Total</th></tr></thead>
            <tbody>
                <?php foreach ($serviceRevenue as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['service']) ?></td>
                        <td class="text-right amount-income"><?= View::formatMoney($row['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Expenses by Category</h2>
        <table class="table">
            <thead><tr><th>Category</th><th class="text-right">Total</th></tr></thead>
            <tbody>
                <?php foreach ($expenseByCategory as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['category_name']) ?></td>
                        <td class="text-right amount-expense"><?= View::formatMoney((float) $row['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<section class="card">
    <h2>Profit by Month</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Expenses</th>
                <th class="text-right">Net Profit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($profitByMonth as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['month']) ?></td>
                    <td class="text-right amount-income"><?= View::formatMoney($row['revenue']) ?></td>
                    <td class="text-right amount-expense"><?= View::formatMoney($row['expenses']) ?></td>
                    <td class="text-right"><?= View::formatMoney($row['net_profit']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card">
    <h2>Revenue by Month</h2>
    <table class="table">
        <thead><tr><th>Month</th><th class="text-right">Revenue</th></tr></thead>
        <tbody>
            <?php foreach ($revenueByMonth as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['month']) ?></td>
                    <td class="text-right amount-income"><?= View::formatMoney((float) $row['total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
