<section class="page-header">
    <div>
        <h1>Record Income</h1>
        <p class="subtitle">Date, customer, service, amount, payment method, invoice</p>
    </div>
</section>

<section class="card card-narrow">
    <form method="post" action="/income" class="form">
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
            <label for="customer_name">Customer</label>
            <input type="text" id="customer_name" name="customer_name" list="customers" placeholder="Customer or company name">
            <datalist id="customers">
                <?php foreach ($customers as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="service_type">Service Type *</label>
                <select id="service_type" name="service_type" required>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= htmlspecialchars($service) ?>"><?= htmlspecialchars($service) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method" required>
                    <?php foreach ($paymentMethods as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="invoice_number">Invoice Number</label>
                <input type="text" id="invoice_number" name="invoice_number" value="<?= htmlspecialchars($suggestedInvoiceNumber) ?>" placeholder="FZ-2026-0001">
            </div>
            <div class="form-group">
                <label for="invoice_id">Link to Invoice</label>
                <select id="invoice_id" name="invoice_id">
                    <option value="">None</option>
                    <?php foreach ($invoices as $inv): ?>
                        <option value="<?= (int) $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?> — <?= htmlspecialchars($inv['customer_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="2"></textarea>
        </div>

        <div class="form-actions">
            <a href="/income" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Income</button>
        </div>
    </form>
</section>
