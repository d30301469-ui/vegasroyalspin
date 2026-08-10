<?php

declare(strict_types=1);

/**
 * Runtime helpers for code living under shared/{api,services}.
 * Thin loaders must require this before the canonical implementation file.
 */

if (!function_exists('shared_project_root')) {
    function shared_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('shared_package_root')) {
    /**
     * Active package root: admin/ when panel boots, otherwise frontend BASE_PATH / monorepo root.
     */
    function shared_package_root(): string
    {
        if (defined('ADMIN_BASE_PATH')) {
            return (string) ADMIN_BASE_PATH;
        }
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return shared_project_root();
    }
}
