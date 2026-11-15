<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Account;
use Foodeza\Bookkeeping\View;

final class AccountController
{
    public function index(): void
    {
        View::render('accounts/index', [
            'accounts' => Account::all(),
            'totals' => Account::totalsByType(),
        ]);
    }

    public function create(): void
    {
        View::render('accounts/create');
    }

    public function store(): void
    {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $description = trim($_POST['description'] ?? '') ?: null;

        if ($name === '' || !in_array($type, ['asset', 'liability', 'income', 'expense'], true)) {
            View::flash('Please fill in all required fields.', 'error');
            View::redirect('/accounts/new');
        }

        Account::create([
            'name' => $name,
            'type' => $type,
            'description' => $description,
        ]);

        View::flash('Account created successfully.');
        View::redirect('/accounts');
    }
}
