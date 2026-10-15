<?php

namespace App\Services;

use App\Models\Admin;
use App\Services\Plugins\InstalledPluginCatalog;
use Illuminate\Http\Request;

final class AdminPermissions
{
    public static function all(): array
    {
        $permissions = ['pricing.view', 'pricing.manage', 'currencies.manage', 'dashboard.view', 'inventory.manage', 'orders.payment', 'orders.receipts', 'media.private', 'newsletter.export', 'staff.manage', 'audit.view', 'extensions.view', 'extensions.manage', 'themes.view', 'themes.manage'];
        foreach (['products', 'categories', 'occasions', 'home-slides', 'delivery-zones', 'orders', 'customers', 'coupons', 'testimonials', 'newsletter', 'faqs', 'contact-messages', 'seo', 'settings', 'media'] as $domain) {
            foreach (['view', 'manage', 'delete'] as $action) {
                $permissions[] = $domain.'.'.$action;
            }
        }

        return array_values(array_unique([...$permissions, ...array_keys(app(InstalledPluginCatalog::class)->load()->permissions())]));
    }

    public static function roles(): array
    {
        return [
            'admin' => [], // Existing ordinary accounts need explicit grants.
            'catalog' => ['products.view', 'products.manage', 'categories.view', 'categories.manage', 'occasions.view', 'occasions.manage', 'inventory.manage', 'media.view', 'media.manage'],
            'fulfillment' => ['orders.view', 'orders.manage'],
            'support' => ['orders.view', 'customers.view', 'contact-messages.view', 'contact-messages.manage'],
            'custom' => [],
        ];
    }

    public static function required(string $name, ?Request $request = null): ?array
    {
        if ($name === 'admin.workspace') {
            return [];
        }
        if (in_array($name, ['admin.dashboard', 'admin.chart.data'], true)) {
            return ['dashboard.view'];
        }
        $parts = explode('.', $name);
        $domain = $parts[1] ?? '';
        $action = implode('.', array_slice($parts, 2));
        if ($domain === 'themes') {
            return match ($action) {
                'index' => ['themes.view'], 'activate', 'settings', 'preview' => ['themes.manage'], default => null
            };
        }
        if ($domain === 'extensions') {
            return match ($action) {
                'index' => ['extensions.view'], 'update', 'settings' => ['extensions.manage'], default => null
            };
        }
        if ($domain === 'staff') {
            return ['staff.manage'];
        }
        if ($domain === 'audit') {
            return ['audit.view', 'extensions.view', 'extensions.manage'];
        }
        if (! in_array($domain.'.view', self::all(), true)) {
            return null; // Unmapped routes fail closed, including for super admins.
        }
        if (! in_array($action, ['index', 'show', 'create', 'edit', 'store', 'update', 'destroy', 'bulk', 'images.delete', 'poll', 'invoice', 'receipt', 'status', 'export', 'approve', 'resolve', 'sitemap.regenerate', 'picker', 'file', 'retry', 'retry-cleanup'], true)) {
            return null;
        }
        $permission = $domain.'.manage';
        if (in_array($action, ['index', 'show', 'poll', 'invoice', 'picker', 'file'], true)) {
            $permission = $domain.'.view';
        } elseif ($action === 'destroy' || $action === 'images.delete' || ($action === 'bulk' && $request?->input('bulk_action') === 'delete')) {
            $permission = $domain.'.delete';
        }
        if ($action === 'receipt') {
            return ['orders.view', 'orders.receipts'];
        }
        if ($domain === 'newsletter' && $action === 'export') {
            return ['newsletter.view', 'newsletter.export'];
        }
        $required = [$permission];
        if ($domain === 'media' && $request) {
            $asset = $request->route('asset');
            if ($request->input('visibility') === 'private' || ($asset && $asset->visibility === 'private')) {
                $required[] = 'media.private';
            }
        }
        if ($domain === 'orders' && ($action === 'status' || ($action === 'bulk' && $request?->input('bulk_action') === 'set_payment'))) {
            // A status form always supplies payment_status. Compare it to persisted state.
            if ($action === 'bulk') {
                $required = ['orders.payment'];
            } elseif ($request) {
                $order = $request->route('order');
                if ($order && $request->input('payment_status') !== $order->payment_status) {
                    $required[] = 'orders.payment';
                }
            }
        }

        return $required;
    }

    public static function canRoute(Admin $admin, string $route): bool
    {
        $required = self::required($route);

        return $required !== null && collect($required)->every(fn ($permission) => $admin->hasPermission($permission));
    }
}
