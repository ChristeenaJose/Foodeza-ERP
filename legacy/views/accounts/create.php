<section class="page-header">
    <div>
        <h1>New Account</h1>
        <p class="subtitle">Add to chart of accounts</p>
    </div>
</section>

<section class="card card-narrow">
    <form method="post" action="/accounts" class="form">
        <div class="form-group">
            <label for="name">Account Name *</label>
            <input type="text" id="name" name="name" placeholder="e.g. Delivery Platform Payouts" required>
        </div>

        <div class="form-group">
            <label for="type">Account Type *</label>
            <select id="type" name="type" required>
                <option value="">Select type</option>
                <option value="asset">Asset — cash, bank, equipment</option>
                <option value="liability">Liability — loans, payables</option>
                <option value="income">Income — revenue streams</option>
                <option value="expense">Expense — cost categories</option>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" placeholder="Optional description"></textarea>
        </div>

        <div class="form-actions">
            <a href="/accounts" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
    </form>
</section>
