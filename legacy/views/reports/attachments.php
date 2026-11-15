<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Receipts & Attachments</h1>
        <p class="subtitle">Expense receipts and invoice PDF copies</p>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Expense Receipts</h2>
        <?php if (empty($receipts)): ?>
            <p class="empty">No receipts uploaded.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $r): ?>
                        <tr>
                            <td><a href="/attachments/download?id=<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['original_name']) ?></a></td>
                            <td><?= htmlspecialchars($r['date']) ?></td>
                            <td><?= htmlspecialchars($r['supplier_name'] ?? '—') ?></td>
                            <td class="text-right"><?= View::formatMoney((float) $r['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Invoice Copies</h2>
        <?php if (empty($invoiceFiles)): ?>
            <p class="empty">No invoice files stored.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceFiles as $f): ?>
                        <tr>
                            <td><a href="/attachments/download?id=<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['invoice_number']) ?></a></td>
                            <td><?= htmlspecialchars($f['date']) ?></td>
                            <td class="text-right"><?= View::formatMoney((float) $f['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
