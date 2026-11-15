<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Income</h1>
        <p class="subtitle">Sales and payments received</p>
    </div>
    <a href="/income/new" class="btn btn-primary">+ Record Income</a>
</div>

<section class="card">
    <?php if (empty($incomes)): ?>
        <p class="empty">No income recorded. <a href="/income/new">Add your first entry</a>.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Payment</th>
                    <th>Invoice #</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">VAT</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomes as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['service_type']) ?></td>
                        <td><?= htmlspecialchars(View::paymentMethod($row['payment_method'])) ?></td>
                        <td><?= htmlspecialchars($row['invoice_number'] ?? '—') ?></td>
                        <td class="text-right amount-income"><?= View::formatMoney((float) $row['amount']) ?></td>
                        <td class="text-right muted"><?= View::formatMoney((float) $row['vat_amount']) ?></td>
                        <td>
                            <form method="post" action="/income/delete" class="inline-form" onsubmit="return confirm('Delete?')">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
