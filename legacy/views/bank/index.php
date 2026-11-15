<?php use Foodeza\Bookkeeping\View; ?>
<div class="page-header">
    <div>
        <h1>Bank Transactions</h1>
        <p class="subtitle">Import CSV and match to income or expenses</p>
    </div>
</div>

<section class="card">
    <h2>Import Bank CSV</h2>
    <p class="muted" style="margin-bottom: 1rem;">CSV must include columns: date, description, amount (reference optional).</p>
    <form method="post" action="/bank/import" enctype="multipart/form-data" class="filters">
        <div class="form-group">
            <input type="file" name="csv" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
    </form>
</section>

<section class="card">
    <h2>Unmatched Transactions (<?= count($unmatched) ?>)</h2>
    <?php if (empty($unmatched)): ?>
        <p class="empty">All bank transactions are matched.</p>
    <?php else: ?>
        <?php foreach ($unmatched as $bt): ?>
            <div class="match-row">
                <div class="match-info">
                    <strong><?= htmlspecialchars($bt['date']) ?></strong>
                    <?= htmlspecialchars($bt['description']) ?>
                    <span class="<?= $bt['amount'] >= 0 ? 'amount-income' : 'amount-expense' ?>">
                        <?= View::formatMoney(abs((float) $bt['amount'])) ?>
                    </span>
                </div>
                <form method="post" action="/bank/match" class="match-form">
                    <input type="hidden" name="bank_id" value="<?= (int) $bt['id'] ?>">
                    <?php if ($bt['amount'] >= 0): ?>
                        <select name="income_id" required>
                            <option value="">Match to income…</option>
                            <?php foreach ($recentIncome as $inc): ?>
                                <option value="<?= (int) $inc['id'] ?>">
                                    <?= htmlspecialchars($inc['date']) ?> — <?= htmlspecialchars($inc['customer_name'] ?? $inc['service_type']) ?> (<?= View::formatMoney((float) $inc['amount']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <select name="expense_id" required>
                            <option value="">Match to expense…</option>
                            <?php foreach ($recentExpenses as $exp): ?>
                                <option value="<?= (int) $exp['id'] ?>">
                                    <?= htmlspecialchars($exp['date']) ?> — <?= htmlspecialchars($exp['supplier_name'] ?? $exp['category_name']) ?> (<?= View::formatMoney((float) $exp['amount']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-secondary btn-sm">Match</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="card">
    <h2>All Bank Transactions</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
                <th>Matched</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $bt): ?>
                <tr>
                    <td><?= htmlspecialchars($bt['date']) ?></td>
                    <td><?= htmlspecialchars($bt['description']) ?></td>
                    <td class="text-right"><?= View::formatMoney((float) $bt['amount']) ?></td>
                    <td>
                        <?php if ($bt['matched_income_id']): ?>
                            Income #<?= (int) $bt['matched_income_id'] ?>
                        <?php elseif ($bt['matched_expense_id']): ?>
                            Expense #<?= (int) $bt['matched_expense_id'] ?>
                        <?php else: ?>
                            <span class="muted">Unmatched</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
