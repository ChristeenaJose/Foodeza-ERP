<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Outstanding Invoices</h1>
        <p class="subtitle">Unpaid and partially paid invoices</p>
    </div>
    <a href="/invoices/new" class="btn btn-primary">+ Create Invoice</a>
</div>

<section class="card">
    <?php if (empty($invoices)): ?>
        <p class="empty">No outstanding invoices.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="/invoices/show?id=<?= (int) $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                        <td><?= htmlspecialchars($inv['date']) ?></td>
                        <td><?= htmlspecialchars($inv['due_date'] ?? '—') ?></td>
                        <td><span class="badge <?= View::statusBadge($inv['status']) ?>"><?= ucfirst($inv['status']) ?></span></td>
                        <td class="text-right"><?= View::formatMoney((float) $inv['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">Total Outstanding</th>
                    <th class="text-right"><?= View::formatMoney(array_sum(array_column($invoices, 'total'))) ?></th>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</section>
