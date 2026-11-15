<section class="page-header">
    <div>
        <h1>New Transaction</h1>
        <p class="subtitle">Record income or expense</p>
    </div>
</section>

<section class="card card-narrow">
    <form method="post" action="/transactions" class="form" id="transaction-form">
        <div class="form-row">
            <div class="form-group">
                <label for="type">Type *</label>
                <select id="type" name="type" required>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <div class="form-group">
                <label for="date">Date *</label>
                <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description *</label>
            <input type="text" id="description" name="description" placeholder="e.g. Corporate lunch — Acme Corp, 45 meals" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="amount">Amount (<?= htmlspecialchars($config['currency']) ?>) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label for="account_id">Account *</label>
                <select id="account_id" name="account_id" required>
                    <option value="">Select account</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= (int) $account['id'] ?>">
                            <?= htmlspecialchars($account['name']) ?> (<?= ucfirst($account['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group" id="service-group">
            <label for="category_id" id="category-label">Service</label>
            <select id="category_id" name="category_id">
                <option value="">Select service</option>
                <optgroup label="Foodeza Services" id="income-categories">
                    <?php foreach ($incomeCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" data-type="income"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Expense Categories" id="expense-categories">
                    <?php foreach ($expenseCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" data-type="expense"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="reference">Reference</label>
                <input type="text" id="reference" name="reference" placeholder="Invoice #, receipt #">
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <input type="text" id="notes" name="notes" placeholder="Optional notes">
            </div>
        </div>

        <div class="form-actions">
            <a href="/transactions" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Transaction</button>
        </div>
    </form>
</section>

<script>
document.getElementById('type').addEventListener('change', function () {
    const type = this.value;
    const select = document.getElementById('category_id');
    const label = document.getElementById('category-label');
    label.textContent = type === 'income' ? 'Service *' : 'Category';
    select.required = type === 'income';

    Array.from(select.options).forEach(opt => {
        if (!opt.dataset.type) return;
        opt.hidden = opt.dataset.type !== type;
        if (opt.hidden && opt.selected) select.value = '';
    });
});
document.getElementById('type').dispatchEvent(new Event('change'));
</script>
