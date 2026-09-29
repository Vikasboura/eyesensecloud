<?php

function get_hostel_settings_file_path()
{
    return __DIR__ . '/hostel_settings.json';
}

function load_hostel_settings()
{
    $path = get_hostel_settings_file_path();
    if (!file_exists($path)) {
        return [
            'clients' => [],
            'updated_at' => null,
        ];
    }

    $json = @file_get_contents($path);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [
            'clients' => [],
            'updated_at' => null,
        ];
    }

    // Backward compatibility: if older format used top-level 'enabled', convert to clients map 'global'
    if (isset($data['enabled']) && !isset($data['clients'])) {
        $global = [
            'clients' => [
                '__GLOBAL__' => [
                    'enabled' => !empty($data['enabled']),
                    'updated_at' => $data['updated_at'] ?? null,
                ]
            ],
            'updated_at' => $data['updated_at'] ?? null,
        ];
        return $global;
    }

    return array_merge([
        'clients' => [],
        'updated_at' => null,
    ], $data);
}

function save_hostel_settings(array $settings)
{
    $path = get_hostel_settings_file_path();
    @file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function is_hostel_enabled_for_client(string $clientId)
{
    $settings = load_hostel_settings();
    if (empty($clientId)) return false;
    if (!empty($settings['clients'][$clientId])) {
        return !empty($settings['clients'][$clientId]['enabled']);
    }
    // No per-client setting => treat as disabled (no global fallback)
    return false;
}

function set_hostel_enabled_for_client(string $clientId, bool $enabled)
{
    $settings = load_hostel_settings();
    if (empty($clientId)) return $settings;
    if (!isset($settings['clients']) || !is_array($settings['clients'])) {
        $settings['clients'] = [];
    }
    $settings['clients'][$clientId] = [
        'enabled' => $enabled,
        'updated_at' => date('c'),
    ];
    $settings['updated_at'] = date('c');
    // If a legacy/global entry exists, remove it to avoid global overrides
    if (isset($settings['clients']['__GLOBAL__'])) {
        unset($settings['clients']['__GLOBAL__']);
    }
    save_hostel_settings($settings);
    return $settings;
}

function is_hostel_enabled()
{
    // Legacy convenience: return true if any client has it enabled
    $settings = load_hostel_settings();
    if (empty($settings['clients']) || !is_array($settings['clients'])) return false;
    foreach ($settings['clients'] as $c) {
        if (!empty($c['enabled'])) return true;
    }
    return false;
}

