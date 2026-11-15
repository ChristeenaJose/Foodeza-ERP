<?php use Foodeza\Bookkeeping\View; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 2rem auto; color: #1a1a2e; }
        h1 { color: #dc2626; margin-bottom: 0; }
        .meta { margin: 1.5rem 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e5e2dc; text-align: left; }
        .total { font-size: 1.25rem; font-weight: bold; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($config['company']) ?></h1>
    <p>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></p>

    <div class="meta">
        <p><strong>Bill to:</strong> <?= htmlspecialchars($invoice['customer_name']) ?></p>
        <?php if ($invoice['customer_address']): ?>
            <p><?= nl2br(htmlspecialchars($invoice['customer_address'])) ?></p>
        <?php endif; ?>
        <p><strong>Date:</strong> <?= htmlspecialchars($invoice['date']) ?></p>
        <?php if ($invoice['due_date']): ?>
            <p><strong>Due:</strong> <?= htmlspecialchars($invoice['due_date']) ?></p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr><th>Description</th><th>Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($invoice['service_type']) ?></td>
                <td><?= View::formatMoney((float) $invoice['subtotal']) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr><td>VAT (<?= (int) ((float) $invoice['vat_rate'] * 100) ?>%)</td><td><?= View::formatMoney((float) $invoice['vat_amount']) ?></td></tr>
            <tr><td class="total">Total</td><td class="total"><?= View::formatMoney((float) $invoice['total']) ?></td></tr>
        </tfoot>
    </table>

    <?php if ($invoice['notes']): ?>
        <p style="margin-top: 2rem;"><strong>Notes:</strong> <?= htmlspecialchars($invoice['notes']) ?></p>
    <?php endif; ?>
</body>
</html>
