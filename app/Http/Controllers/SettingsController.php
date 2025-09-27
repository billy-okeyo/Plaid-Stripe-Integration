<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get all settings
     */
    public function index(): JsonResponse
    {
        try {
            $settings = Setting::getAllSettings();

            return response()->json([
                'success' => true,
                'settings' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific setting
     */
    public function show(string $key): JsonResponse
    {
        try {
            $value = Setting::get($key);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'Setting not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'key' => $key,
                'value' => $value
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get setting', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a setting
     */
    public function update(Request $request, string $key): JsonResponse
    {
        try {
            $request->validate([
                'value' => 'required',
                'type' => ['sometimes', Rule::in(['string', 'boolean', 'integer', 'float', 'json', 'array'])],
                'description' => 'sometimes|string|nullable'
            ]);

            $type = $request->input('type', 'string');
            $description = $request->input('description');

            Setting::set($key, $request->input('value'), $type, $description);

            Log::info('Setting updated', [
                'key' => $key,
                'value' => $request->input('value'),
                'type' => $type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'key' => $key,
                'value' => Setting::get($key)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update setting', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle service availability (for testing purposes)
     */
    public function toggleService(Request $request, string $service): JsonResponse
    {
        try {
            $request->validate([
                'enabled' => 'required|boolean'
            ]);

            $enabled = $request->input('enabled');

            // Validate service name
            if (!in_array($service, ['plaid', 'stripe'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid service name. Must be plaid or stripe.'
                ], 400);
            }

            Setting::toggleService($service, $enabled);

            Log::info('Service toggled', [
                'service' => $service,
                'enabled' => $enabled
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($service) . ' service ' . ($enabled ? 'enabled' : 'disabled'),
                'service' => $service,
                'enabled' => $enabled
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle service', [
                'service' => $service,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service statuses
     */
    public function getServiceStatuses(): JsonResponse
    {
        try {
            $statuses = [
                'plaid' => [
                    'enabled' => Setting::isServiceEnabled('plaid'),
                    'description' => 'Plaid service for bank account linking and data retrieval'
                ],
                'stripe' => [
                    'enabled' => Setting::isServiceEnabled('stripe'),
                    'description' => 'Stripe service for payment processing and ACH transfers'
                ]
            ];

            return response()->json([
                'success' => true,
                'services' => $statuses
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get service statuses', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset all settings to defaults
     */
    public function resetToDefaults(): JsonResponse
    {
        try {
            // Reset service settings
            Setting::set('plaid_enabled', true, 'boolean', 'Enable/disable Plaid service for testing queue functionality');
            Setting::set('stripe_enabled', true, 'boolean', 'Enable/disable Stripe service for testing queue functionality');
            Setting::set('queue_auto_retry', true, 'boolean', 'Automatically retry queued transactions when services become available');
            Setting::set('max_retry_attempts', 3, 'integer', 'Maximum number of retry attempts for failed transactions');

            Log::info('Settings reset to defaults');

            return response()->json([
                'success' => true,
                'message' => 'All settings reset to defaults'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'required',
                'settings.*.type' => ['sometimes', Rule::in(['string', 'boolean', 'integer', 'float', 'json', 'array'])],
                'settings.*.description' => 'sometimes|string|nullable'
            ]);

            $updated = [];

            foreach ($request->input('settings') as $settingData) {
                $key = $settingData['key'];
                $value = $settingData['value'];
                $type = $settingData['type'] ?? 'string';
                $description = $settingData['description'] ?? null;

                Setting::set($key, $value, $type, $description);
                $updated[] = $key;
            }

            Log::info('Bulk settings update', ['updated_keys' => $updated]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'updated' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk update settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
