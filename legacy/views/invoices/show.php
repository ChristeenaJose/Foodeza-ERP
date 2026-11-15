<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($invoice['invoice_number']) ?></h1>
        <p class="subtitle"><?= htmlspecialchars($invoice['customer_name']) ?> — <?= htmlspecialchars($invoice['service_type']) ?></p>
    </div>
    <div class="btn-group">
        <a href="/invoices/pdf?id=<?= (int) $invoice['id'] ?>" class="btn btn-secondary" target="_blank">View PDF</a>
        <?php if ($invoice['status'] !== 'paid'): ?>
            <form method="post" action="/invoices/paid" class="inline-form">
                <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
                <button type="submit" class="btn btn-primary">Mark Paid</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="card card-narrow">
    <dl class="detail-list">
        <div><dt>Status</dt><dd><span class="badge <?= View::statusBadge($invoice['status']) ?>"><?= ucfirst($invoice['status']) ?></span></dd></div>
        <div><dt>Date</dt><dd><?= htmlspecialchars($invoice['date']) ?></dd></div>
        <div><dt>Due Date</dt><dd><?= htmlspecialchars($invoice['due_date'] ?? '—') ?></dd></div>
        <div><dt>Subtotal</dt><dd><?= View::formatMoney((float) $invoice['subtotal']) ?></dd></div>
        <div><dt>VAT (<?= (int) ((float) $invoice['vat_rate'] * 100) ?>%)</dt><dd><?= View::formatMoney((float) $invoice['vat_amount']) ?></dd></div>
        <div><dt>Total</dt><dd><strong><?= View::formatMoney((float) $invoice['total']) ?></strong></dd></div>
        <?php if ($invoice['notes']): ?>
            <div><dt>Notes</dt><dd><?= nl2br(htmlspecialchars($invoice['notes'])) ?></dd></div>
        <?php endif; ?>
    </dl>
</section>
