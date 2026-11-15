import { createRouter, createWebHistory } from 'vue-router'
import Layout from '../framework/components/Layout.vue'

const bookkeepingNav = [
  { to: '/bookkeeping', label: 'Dashboard', exact: true },
  { to: '/bookkeeping/income', label: 'Income' },
  { to: '/bookkeeping/expenses', label: 'Expenses' },
  { to: '/bookkeeping/onam', label: 'Onam Köln 26' },
  { to: '/bookkeeping/invoices', label: 'Invoices' },
  { to: '/bookkeeping/payments', label: 'Payments' },
  { to: '/bookkeeping/bank', label: 'Bank' },
  { to: '/bookkeeping/reports', label: 'Reports' },
  { to: '/bookkeeping/receipts', label: 'Receipts' },
]

const bookingNav = [
  { to: '/bookings', label: 'Calendar', exact: true },
  { to: '/bookings/list', label: 'All bookings' },
]

const inventoryNav = [
  { to: '/inventory', label: 'Stock', exact: true },
  { to: '/inventory/movements', label: 'Movements' },
]

const orderNav = [
  { to: '/orders', label: 'Events', exact: true },
]

const menuNav = [
  { to: '/menu', label: 'Dashboard', exact: true },
  { to: '/menu/products', label: 'Products' },
  { to: '/menu/categories', label: 'Categories' },
  { to: '/menu/menus', label: 'Menus' },
  { to: '/menu/packages', label: 'Packages' },
  { to: '/menu/addons', label: 'Add-ons' },
  { to: '/menu/pricing', label: 'Pricing' },
  { to: '/menu/availability', label: 'Availability' },
  { to: '/menu/settings', label: 'Settings' },
]

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'erp-home', component: () => import('../framework/ErpHomeView.vue') },
    {
      path: '/bookkeeping',
      component: Layout,
      meta: { module: 'Bookkeeping', nav: bookkeepingNav },
      children: [
        { path: '', name: 'dashboard', component: () => import('@modules/bookkeeping/frontend/DashboardView.vue') },
        { path: 'income', name: 'income', component: () => import('@modules/bookkeeping/frontend/IncomeView.vue') },
        { path: 'income/new', name: 'income-new', component: () => import('@modules/bookkeeping/frontend/IncomeFormView.vue') },
        { path: 'income/:id/edit', name: 'income-edit', component: () => import('@modules/bookkeeping/frontend/IncomeFormView.vue') },
        { path: 'expenses', name: 'expenses', component: () => import('@modules/bookkeeping/frontend/ExpensesView.vue') },
        { path: 'expenses/new', name: 'expenses-new', component: () => import('@modules/bookkeeping/frontend/ExpenseFormView.vue') },
        { path: 'expenses/:id/edit', name: 'expenses-edit', component: () => import('@modules/bookkeeping/frontend/ExpenseFormView.vue') },
        { path: 'invoices', name: 'invoices', component: () => import('@modules/bookkeeping/frontend/InvoicesView.vue') },
        { path: 'invoices/new', name: 'invoices-new', component: () => import('@modules/bookkeeping/frontend/InvoiceFormView.vue') },
        { path: 'invoices/:id', name: 'invoice-detail', component: () => import('@modules/bookkeeping/frontend/InvoiceDetailView.vue') },
        { path: 'payments', name: 'payments', component: () => import('@modules/bookkeeping/frontend/PaymentsView.vue') },
        { path: 'payments/new', name: 'payments-new', component: () => import('@modules/bookkeeping/frontend/PaymentFormView.vue') },
        { path: 'bank', name: 'bank', component: () => import('@modules/bookkeeping/frontend/BankView.vue') },
        { path: 'reports', name: 'reports', component: () => import('@modules/bookkeeping/frontend/ReportsView.vue') },
        { path: 'reports/tax', name: 'tax-report', component: () => import('@modules/bookkeeping/frontend/TaxReportView.vue') },
        { path: 'reports/vat', name: 'vat', component: () => import('@modules/bookkeeping/frontend/VatView.vue') },
        { path: 'onam', name: 'onam', component: () => import('@modules/bookkeeping/frontend/OnamView.vue') },
        { path: 'receipts', name: 'receipts', component: () => import('@modules/bookkeeping/frontend/ReceiptsView.vue') },
      ],
    },
    {
      path: '/bookings',
      component: Layout,
      meta: { module: 'Booking management', nav: bookingNav },
      children: [
        { path: '', name: 'bookings-calendar', component: () => import('@modules/bookings/frontend/BookingCalendarView.vue') },
        { path: 'list', name: 'bookings-list', component: () => import('@modules/bookings/frontend/BookingsView.vue') },
        { path: 'new', name: 'bookings-new', component: () => import('@modules/bookings/frontend/BookingFormView.vue') },
        { path: ':id/edit', name: 'bookings-edit', component: () => import('@modules/bookings/frontend/BookingFormView.vue') },
      ],
    },
    {
      path: '/inventory',
      component: Layout,
      meta: { module: 'Inventory', nav: inventoryNav },
      children: [
        { path: '', name: 'inventory', component: () => import('@modules/inventory/frontend/InventoryView.vue') },
        { path: 'new', name: 'inventory-new', component: () => import('@modules/inventory/frontend/InventoryFormView.vue') },
        { path: 'movements', name: 'inventory-movements', component: () => import('@modules/inventory/frontend/InventoryMovementsView.vue') },
        { path: ':id/edit', name: 'inventory-edit', component: () => import('@modules/inventory/frontend/InventoryFormView.vue') },
      ],
    },
    {
      path: '/menu',
      component: Layout,
      meta: { module: 'Menu management', nav: menuNav },
      children: [
        { path: '', name: 'menu-dashboard', component: () => import('@modules/menu/frontend/MenuDashboardView.vue') },
        { path: 'products', name: 'menu-products', component: () => import('@modules/menu/frontend/ProductsView.vue') },
        { path: 'products/new', name: 'menu-products-new', component: () => import('@modules/menu/frontend/ProductFormView.vue') },
        { path: 'products/:id/edit', name: 'menu-products-edit', component: () => import('@modules/menu/frontend/ProductFormView.vue') },
        { path: 'categories', name: 'menu-categories', component: () => import('@modules/menu/frontend/CategoriesView.vue') },
        { path: 'menus', name: 'menu-menus', component: () => import('@modules/menu/frontend/MenusView.vue') },
        { path: 'menus/new', name: 'menu-menus-new', component: () => import('@modules/menu/frontend/MenuBuilderView.vue') },
        { path: 'menus/:id', name: 'menu-menus-edit', component: () => import('@modules/menu/frontend/MenuBuilderView.vue') },
        { path: 'packages', name: 'menu-packages', component: () => import('@modules/menu/frontend/PackagesView.vue') },
        { path: 'packages/new', name: 'menu-packages-new', component: () => import('@modules/menu/frontend/PackagesView.vue') },
        { path: 'packages/:id/edit', name: 'menu-packages-edit', component: () => import('@modules/menu/frontend/PackagesView.vue') },
        { path: 'addons', name: 'menu-addons', component: () => import('@modules/menu/frontend/MenuOpsView.vue'), meta: { opsMode: 'addons' } },
        { path: 'pricing', name: 'menu-pricing', component: () => import('@modules/menu/frontend/MenuOpsView.vue'), meta: { opsMode: 'pricing' } },
        { path: 'availability', name: 'menu-availability', component: () => import('@modules/menu/frontend/MenuOpsView.vue'), meta: { opsMode: 'availability' } },
        { path: 'settings', name: 'menu-settings', component: () => import('@modules/menu/frontend/MenuOpsView.vue'), meta: { opsMode: 'settings' } },
        // Legacy simple menu form redirects
        { path: 'new', redirect: '/menu/products/new' },
        { path: ':id/edit', redirect: (to) => `/menu/products/${to.params.id}/edit` },
      ],
    },
    {
      path: '/orders',
      component: Layout,
      meta: { module: 'Google Form orders', nav: orderNav },
      children: [
        { path: '', name: 'google-form-orders', component: () => import('@modules/google-form-orders/frontend/GoogleFormOrdersView.vue') },
        { path: 'new', name: 'google-form-orders-event-new', component: () => import('@modules/google-form-orders/frontend/GoogleFormOrderEventFormView.vue') },
        { path: 'entries/:id/edit', name: 'google-form-orders-entry-edit', component: () => import('@modules/google-form-orders/frontend/GoogleFormOrderFormView.vue') },
        { path: ':id/edit', name: 'google-form-orders-event-edit', component: () => import('@modules/google-form-orders/frontend/GoogleFormOrderEventFormView.vue') },
        { path: ':id', name: 'google-form-orders-event', component: () => import('@modules/google-form-orders/frontend/GoogleFormOrderEventDetailView.vue') },
      ],
    },
    { path: '/income/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/expenses/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/invoices/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/payments/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/bank/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/reports/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/onam/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/receipts/:pathMatch(.*)*', redirect: (to) => `/bookkeeping${to.path}` },
    { path: '/income', redirect: '/bookkeeping/income' },
    { path: '/expenses', redirect: '/bookkeeping/expenses' },
    { path: '/invoices', redirect: '/bookkeeping/invoices' },
    { path: '/payments', redirect: '/bookkeeping/payments' },
    { path: '/bank', redirect: '/bookkeeping/bank' },
    { path: '/reports', redirect: '/bookkeeping/reports' },
    { path: '/onam', redirect: '/bookkeeping/onam' },
    { path: '/receipts', redirect: '/bookkeeping/receipts' },
  ],
})

export default router
