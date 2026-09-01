<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */


if (!function_exists('inventory_quantity_label')) {
    function inventory_quantity_label(float $quantity, string $unit = ''): string
    {
        $text = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
        return $text . ($unit !== '' ? ' ' . $unit : '');
    }
}

if (!function_exists('inventory_measurement_label')) {
    function inventory_measurement_label(float $quantity, string $measurementType = 'STANDARD', string $unit = ''): string
    {
        // Stock quantity is NEVER a length. Quantity and size are separate fields.
        return inventory_quantity_label($quantity, $unit);
    }
}

if (!function_exists('inventory_size_label')) {
    function inventory_size_label(?float $size, ?string $unit, ?float $inches = null): string
    {
        if ($size === null || $size <= 0 || $unit === null || $unit === '') {
            return '—';
        }
        $unit = strtoupper($unit);
        $inches = $inches ?? ($unit === 'MM' ? $size / 25.4 : $size);
        $mm = $inches * 25.4;
        $left = rtrim(rtrim(number_format($size, 3, '.', ''), '0'), '.') . ' ' . $unit;
        $right = $unit === 'MM'
            ? rtrim(rtrim(number_format($inches, 3, '.', ''), '0'), '.') . ' in'
            : rtrim(rtrim(number_format($mm, 2, '.', ''), '0'), '.') . ' mm';
        return $left . ' · ' . $right;
    }
}
