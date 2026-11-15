<section class="page-header">
    <div>
        <h1>Record Payment</h1>
        <p class="subtitle">Link to invoice or income entry</p>
    </div>
</section>

<section class="card card-narrow">
    <form method="post" action="/payments" class="form">
        <div class="form-row">
            <div class="form-group">
                <label for="date">Date *</label>
                <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label for="amount">Amount *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
            </div>
        </div>

        <div class="form-group">
            <label for="payment_method">Payment Method *</label>
            <select id="payment_method" name="payment_method" required>
                <?php foreach ($paymentMethods as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="invoice_id">Link to Invoice</label>
            <select id="invoice_id" name="invoice_id">
                <option value="">None</option>
                <?php foreach ($invoices as $inv): ?>
                    <option value="<?= (int) $inv['id'] ?>">
                        <?= htmlspecialchars($inv['invoice_number']) ?> — <?= htmlspecialchars($inv['customer_name']) ?> (<?= htmlspecialchars($inv['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="reference">Reference</label>
            <input type="text" id="reference" name="reference" placeholder="Transaction ID, receipt #">
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="2"></textarea>
        </div>

        <div class="form-actions">
            <a href="/payments" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Payment</button>
        </div>
    </form>
</section>
