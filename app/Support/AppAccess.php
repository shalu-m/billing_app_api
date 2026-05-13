<?php

namespace App\Support;

class AppAccess
{
    public static function isShopEnabled(string $shopKey): bool
    {
        return array_key_exists($shopKey, config('app.shops', []))
            && in_array($shopKey, config('app.enabled_shops', []), true);
    }

    public static function roleCan(string $role, string $permission): bool
    {
        $permissions = config("app.role_permissions.{$role}", []);

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public static function navigationForRole(string $role): array
    {
        $availableShops = [];

        foreach (config('app.shops', []) as $shopKey => $shop) {
            if (!self::isShopEnabled($shopKey)) {
                continue;
            }

            $pages = collect($shop['pages'] ?? [])
                ->filter(fn ($page) => self::roleCan($role, $page['permission'] ?? "{$shopKey}.{$page['key']}"))
                ->map(fn ($page) => [
                    'key' => $page['key'],
                    'label' => $page['label'],
                    'permission' => $page['permission'] ?? "{$shopKey}.{$page['key']}",
                    'title' => $page['title'] ?? $page['label'],
                    'subtitle' => $page['subtitle'] ?? '',
                ])
                ->values()
                ->all();

            if (empty($pages)) {
                continue;
            }

            $pageKeys = array_column($pages, 'key');
            $configuredDefaultPage = $shop['default_page'] ?? $pages[0]['key'];
            $defaultPage = in_array($configuredDefaultPage, $pageKeys, true)
                ? $configuredDefaultPage
                : $pages[0]['key'];

            $availableShops[] = [
                'key' => $shopKey,
                'label' => $shop['label'] ?? $shopKey,
                'default_page' => $defaultPage,
                'pages' => $pages,
            ];
        }

        $shopKeys = array_column($availableShops, 'key');
        $configuredDefaultShop = config('app.default_shop', $shopKeys[0] ?? null);
        $defaultShop = in_array($configuredDefaultShop, $shopKeys, true)
            ? $configuredDefaultShop
            : ($shopKeys[0] ?? null);

        return [
            'default_shop' => $defaultShop,
            'shops' => $availableShops,
        ];
    }

    public static function permissionShop(string $permission): string
    {
        return explode('.', $permission, 2)[0];
    }
}
