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
        $text = number_format(max(0, (int)round($quantity)), 0, '.', '');
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

if (!function_exists('inventory_variant_attributes')) {
    function inventory_variant_attributes($json): array
    {
        if (is_array($json)) return $json;
        if (!is_string($json) || trim($json) === '') return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('inventory_variant_attributes_label')) {
    function inventory_variant_attributes_label($json, string $fallback = 'Default'): string
    {
        $attrs = inventory_variant_attributes($json);
        if (!$attrs) return $fallback;
        $parts = [];
        foreach ($attrs as $key => $value) {
            $label = ucwords(str_replace(['_', '-'], ' ', (string)$key));
            if (is_array($value)) {
                if (array_key_exists('value', $value)) {
                    $v = $value['value'];
                    if (is_numeric($v)) $v = rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
                    $parts[] = $label . ': ' . $v . (!empty($value['unit']) ? ' ' . $value['unit'] : '');
                } else {
                    $parts[] = $label . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } else {
                $parts[] = $label . ': ' . (string)$value;
            }
        }
        return implode(' · ', $parts);
    }
}
