<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Boot the trait
     */
    protected static function bootLogsActivity()
    {
        static::created(function (Model $model) {
            static::logActivity($model, ActivityLog::ACTION_CREATED);
        });

        static::updated(function (Model $model) {
            static::logActivity($model, ActivityLog::ACTION_UPDATED);
        });

        static::deleted(function (Model $model) {
            static::logActivity($model, ActivityLog::ACTION_DELETED);
        });

        // Handle soft delete restoration
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                static::logActivity($model, ActivityLog::ACTION_RESTORED);
            });
        }
    }

    /**
     * Log the activity
     */
    protected static function logActivity(Model $model, string $action): void
    {
        // Skip if no authenticated user
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        
        // Get the tenant_id from the model or user
        $tenantId = $model->tenant_id ?? $user->tenant_id;

        // Cannot log activity without a tenant context
        if (empty($tenantId)) {
            return;
        }

        // Don't log activity for activity logs themselves
        if ($model instanceof ActivityLog) {
            return;
        }

        // Get the changes
        $oldValues = null;
        $newValues = null;
        $changedFields = null;

        if ($action === ActivityLog::ACTION_CREATED) {
            $newValues = static::filterLoggableAttributes($model->getAttributes());
        } elseif ($action === ActivityLog::ACTION_UPDATED) {
            $changes = $model->getChanges();
            $original = $model->getOriginal();
            
            // Filter to only changed attributes
            $changedFields = array_keys($changes);
            $changedFields = array_diff($changedFields, ['updated_at']); // Exclude updated_at
            
            if (empty($changedFields)) {
                return; // Don't log if only updated_at changed
            }

            $oldValues = static::filterLoggableAttributes(
                array_intersect_key($original, array_flip($changedFields))
            );
            $newValues = static::filterLoggableAttributes(
                array_intersect_key($changes, array_flip($changedFields))
            );
            $changedFields = array_values($changedFields);
        } elseif ($action === ActivityLog::ACTION_DELETED) {
            $oldValues = static::filterLoggableAttributes($model->getAttributes());
        }

        ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'model_name' => static::getModelDisplayName($model),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => static::getActivityDescription($model, $action),
        ]);
    }

    /**
     * Filter out sensitive attributes and redact PII from logging
     */
    protected static function filterLoggableAttributes(array $attributes): array
    {
        // Completely remove these fields from logs
        $hidden = ['password', 'remember_token', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes'];
        
        // Get hidden attributes from model if defined
        if (property_exists(static::class, 'hidden')) {
            $hidden = array_merge($hidden, (new static)->getHidden());
        }

        $attributes = array_diff_key($attributes, array_flip($hidden));

        // Redact PII fields — store only masked versions
        $piiFields = [
            'tax_number', 'tax_id', 'ssn', 'social_security_number',
            'account_number', 'bank_account_number', 'routing_number', 'bank_routing_number',
            'swift_code', 'iban',
            'credit_card', 'card_number',
            'salary', 'net_salary', 'gross_salary',
        ];

        // Get model-specific PII fields if defined
        if (property_exists(static::class, 'redactedFields')) {
            $piiFields = array_merge($piiFields, static::$redactedFields);
        }

        foreach ($piiFields as $field) {
            if (isset($attributes[$field]) && $attributes[$field] !== null) {
                $attributes[$field] = static::maskValue((string) $attributes[$field], $field);
            }
        }

        return $attributes;
    }

    /**
     * Mask a PII value, keeping only the last few characters visible
     */
    protected static function maskValue(string $value, string $field): string
    {
        $length = strlen($value);

        // For short values, fully mask
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // For monetary fields, show only that it changed (not the value)
        if (in_array($field, ['salary', 'net_salary', 'gross_salary'])) {
            return '***redacted***';
        }

        // For account/routing numbers, show last 4
        $visible = substr($value, -4);
        return str_repeat('*', $length - 4) . $visible;
    }

    /**
     * Get a human-readable display name for the model
     */
    protected static function getModelDisplayName(Model $model): string
    {
        // Try common name fields
        $nameFields = [
            'invoice_number',
            'bill_number',
            'order_number',
            'receipt_number',
            'payment_number',
            'payroll_number',
            'journal_number',
            'employee_id',
            'account_code',
            'name',
            'title',
            'email',
        ];

        foreach ($nameFields as $field) {
            if (!empty($model->{$field})) {
                return $model->{$field};
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }

    /**
     * Generate a human-readable description
     */
    protected static function getActivityDescription(Model $model, string $action): string
    {
        $modelName = class_basename($model);
        $displayName = static::getModelDisplayName($model);

        return match($action) {
            ActivityLog::ACTION_CREATED => "{$modelName} '{$displayName}' was created",
            ActivityLog::ACTION_UPDATED => "{$modelName} '{$displayName}' was updated",
            ActivityLog::ACTION_DELETED => "{$modelName} '{$displayName}' was deleted",
            ActivityLog::ACTION_RESTORED => "{$modelName} '{$displayName}' was restored",
            default => "{$modelName} '{$displayName}' - {$action}",
        };
    }

    /**
     * Log a custom activity
     */
    public function logCustomActivity(string $action, ?string $description = null, array $properties = []): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'tenant_id' => $this->tenant_id ?? $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->getKey(),
            'model_name' => static::getModelDisplayName($this),
            'new_values' => !empty($properties) ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description ?? static::getActivityDescription($this, $action),
        ]);
    }
}
