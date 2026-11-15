<section class="page-header">
    <div>
        <h1>Record Expense</h1>
        <p class="subtitle">Date, supplier, category, amount, receipt</p>
    </div>
</section>

<section class="card card-narrow">
    <form method="post" action="/expenses" class="form" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="date">Date *</label>
                <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label for="amount">Amount (excl. VAT) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
            </div>
        </div>

        <div class="form-group">
            <label for="supplier_name">Supplier</label>
            <input type="text" id="supplier_name" name="supplier_name" list="suppliers" placeholder="Supplier name">
            <datalist id="suppliers">
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= htmlspecialchars($s['name']) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label for="category_id">Category *</label>
            <select id="category_id" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" placeholder="e.g. Weekly vegetable order">
        </div>

        <div class="form-group">
            <label for="receipt">Receipt Upload</label>
            <input type="file" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
            <small class="muted">PDF, JPEG, PNG, or WebP</small>
        </div>

        <div class="form-actions">
            <a href="/expenses" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Expense</button>
        </div>
    </form>
</section>
