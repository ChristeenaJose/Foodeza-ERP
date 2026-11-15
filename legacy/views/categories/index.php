<section class="page-header">
    <div>
        <h1>Services & Categories</h1>
        <p class="subtitle">Foodeza services and expense categories</p>
    </div>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Foodeza Services</h2>
        <p class="muted" style="margin-bottom: 1rem;">Income is tracked per service line.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>Service</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($config['services'] as $service): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($service) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Expense Categories</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <?php if ($cat['type'] !== 'expense') continue; ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><span class="badge badge-<?= $cat['type'] ?>"><?= ucfirst($cat['type']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 style="margin-top: 1.5rem;">Add Expense Category</h2>
        <form method="post" action="/categories" class="form">
            <input type="hidden" name="type" value="expense">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" placeholder="e.g. Beverage Supplies" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
    </section>
</div>
