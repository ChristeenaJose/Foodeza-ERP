<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Invoices</h1>
        <p class="subtitle">Generate, track paid/unpaid, store PDF copies</p>
    </div>
    <a href="/invoices/new" class="btn btn-primary">+ Create Invoice</a>
</div>

<?php if (!empty($outstanding)): ?>
<section class="card alert-banner">
    <strong><?= count($outstanding) ?> outstanding invoice<?= count($outstanding) === 1 ? '' : 's' ?></strong>
    <span class="muted">Total due: <?= View::formatMoney(array_sum(array_column($outstanding, 'total'))) ?></span>
</section>
<?php endif; ?>

<section class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Status</th>
                <th class="text-right">Total</th>
                <th>PDF</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><a href="/invoices/show?id=<?= (int) $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                    <td><?= htmlspecialchars($inv['date']) ?></td>
                    <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                    <td><?= htmlspecialchars($inv['service_type']) ?></td>
                    <td><span class="badge <?= View::statusBadge($inv['status']) ?>"><?= ucfirst($inv['status']) ?></span></td>
                    <td class="text-right"><?= View::formatMoney((float) $inv['total']) ?></td>
                    <td>
                        <a href="/invoices/pdf?id=<?= (int) $inv['id'] ?>" target="_blank">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
