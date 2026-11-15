<section class="page-header">
    <div>
        <h1>Create Invoice</h1>
        <p class="subtitle">Auto-generated invoice number and PDF copy</p>
    </div>
</section>

<section class="card card-narrow">
    <form method="post" action="/invoices" class="form" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="invoice_number">Invoice Number</label>
                <input type="text" id="invoice_number" name="invoice_number" value="<?= htmlspecialchars($suggestedNumber) ?>">
            </div>
            <div class="form-group">
                <label for="date">Date *</label>
                <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="customer_name">Customer *</label>
            <input type="text" id="customer_name" name="customer_name" list="customers" required>
            <datalist id="customers">
                <?php foreach ($customers as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="service_type">Service *</label>
                <select id="service_type" name="service_type" required>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= htmlspecialchars($service) ?>"><?= htmlspecialchars($service) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input type="date" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="subtotal">Amount (excl. VAT) *</label>
                <input type="number" id="subtotal" name="subtotal" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label for="vat_rate">VAT Rate</label>
                <input type="number" id="vat_rate" name="vat_rate" step="0.01" value="<?= $vatRate ?>" readonly>
            </div>
        </div>

        <div class="form-group">
            <label for="pdf">Upload PDF Copy (optional)</label>
            <input type="file" id="pdf" name="pdf" accept=".pdf">
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="2"></textarea>
        </div>

        <div class="form-actions">
            <a href="/invoices" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Invoice</button>
        </div>
    </form>
</section>
