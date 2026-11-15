<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>VAT / Tax Report</h1>
        <p class="subtitle">Revenue, VAT collected, VAT paid, VAT due</p>
    </div>
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
        <button type="submit" class="btn btn-secondary">Generate</button>
    </form>
</section>

<div class="stats-grid">
    <div class="stat-card income">
        <span class="stat-label">Revenue (excl. VAT)</span>
        <span class="stat-value"><?= View::formatMoney($report['revenue']) ?></span>
    </div>
    <div class="stat-card asset">
        <span class="stat-label">VAT Collected</span>
        <span class="stat-value"><?= View::formatMoney($report['vat_collected']) ?></span>
    </div>
    <div class="stat-card expense">
        <span class="stat-label">VAT Paid</span>
        <span class="stat-value"><?= View::formatMoney($report['vat_paid']) ?></span>
    </div>
    <div class="stat-card <?= $report['vat_due'] >= 0 ? 'positive' : 'negative' ?>">
        <span class="stat-label">VAT Due</span>
        <span class="stat-value"><?= View::formatMoney($report['vat_due']) ?></span>
    </div>
</div>

<section class="card">
    <p class="muted">Designed for small-business VAT reporting. Set <code>vat_rate</code> in config (0 for KOR/small-business rule).</p>
    <p style="margin-top: 1rem;"><strong>Period:</strong> <?= htmlspecialchars($from) ?> — <?= htmlspecialchars($to) ?></p>
    <p><strong>Total expenses (excl. VAT):</strong> <?= View::formatMoney($report['expenses']) ?></p>
</section>
