<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Expenses</h1>
        <p class="subtitle">Supplier purchases and operating costs</p>
    </div>
    <a href="/expenses/new" class="btn btn-primary">+ Record Expense</a>
</div>

<section class="card">
    <?php if (empty($expenses)): ?>
        <p class="empty">No expenses recorded. <a href="/expenses/new">Add your first expense</a>.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Receipt</th>
                    <th class="text-right">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
                        <td><span class="badge badge-expense"><?= htmlspecialchars($row['category_name']) ?></span></td>
                        <td><?= htmlspecialchars($row['description'] ?? '—') ?></td>
                        <td>
                            <?php if ($row['receipt_path']): ?>
                                <a href="/attachments/download?id=<?= (int) $row['receipt_attachment_id'] ?>">View</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-right amount-expense"><?= View::formatMoney((float) $row['amount']) ?></td>
                        <td>
                            <form method="post" action="/expenses/delete" class="inline-form" onsubmit="return confirm('Delete?')">
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
