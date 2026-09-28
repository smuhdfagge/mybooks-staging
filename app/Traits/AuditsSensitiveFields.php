<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Provides field-level audit logging for sensitive data changes.
 *
 * Models using this trait should define a $sensitiveFields property:
 *
 *   protected static array $sensitiveFields = [
 *       'salary' => ['type' => 'monetary', 'label' => 'Base Salary'],
 *       'bank_account_number' => ['type' => 'masked', 'label' => 'Bank Account'],
 *   ];
 *
 * Types:
 *  - monetary: logs direction (increased/decreased/changed) without exposing values
 *  - masked: logs last-4-digit change (****1234 → ****5678)
 *  - reference: logs old → new label (e.g. salary structure name change)
 *  - plain: logs old → new value as-is (for non-sensitive categoricals like status)
 */
trait AuditsSensitiveFields
{
    protected static function bootAuditsSensitiveFields(): void
    {
        static::updating(function (Model $model) {
            if (! Auth::check()) {
                return;
            }

            $fields = static::getSensitiveFieldDefinitions();
            $changes = [];

            foreach ($fields as $field => $config) {
                if (! $model->isDirty($field)) {
                    continue;
                }

                $oldValue = $model->getOriginal($field);
                $newValue = $model->getAttribute($field);

                $changes[] = static::buildFieldChangeEntry($field, $config, $oldValue, $newValue);
            }

            if (empty($changes)) {
                return;
            }

            $user = Auth::user();
            $tenantId = $model->tenant_id ?? $user->tenant_id;

            ActivityLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'action' => 'sensitive_field_changed',
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'model_name' => static::getSensitiveAuditDisplayName($model),
                'old_values' => collect($changes)->pluck('old_display', 'field')->all(),
                'new_values' => collect($changes)->pluck('new_display', 'field')->all(),
                'changed_fields' => collect($changes)->pluck('field')->values()->all(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'description' => static::buildSensitiveChangeDescription($model, $changes),
            ]);
        });
    }

    protected static function getSensitiveFieldDefinitions(): array
    {
        return property_exists(static::class, 'sensitiveFields')
            ? static::$sensitiveFields
            : [];
    }

    protected static function buildFieldChangeEntry(string $field, array $config, mixed $oldValue, mixed $newValue): array
    {
        $type = $config['type'] ?? 'plain';
        $label = $config['label'] ?? ucwords(str_replace('_', ' ', $field));

        return match ($type) {
            'monetary' => [
                'field' => $field,
                'label' => $label,
                'old_display' => '***',
                'new_display' => static::monetaryDirection($oldValue, $newValue),
            ],
            'masked' => [
                'field' => $field,
                'label' => $label,
                'old_display' => static::maskLast4($oldValue),
                'new_display' => static::maskLast4($newValue),
            ],
            'reference' => [
                'field' => $field,
                'label' => $label,
                'old_display' => static::resolveReference($config, $oldValue),
                'new_display' => static::resolveReference($config, $newValue),
            ],
            default => [
                'field' => $field,
                'label' => $label,
                'old_display' => (string) ($oldValue ?? '(none)'),
                'new_display' => (string) ($newValue ?? '(none)'),
            ],
        };
    }

    protected static function monetaryDirection(mixed $old, mixed $new): string
    {
        $oldNum = (float) ($old ?? 0);
        $newNum = (float) ($new ?? 0);

        if ($newNum > $oldNum) {
            return 'increased';
        } elseif ($newNum < $oldNum) {
            return 'decreased';
        }

        return 'changed';
    }

    protected static function maskLast4(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }

        $str = (string) $value;
        $len = strlen($str);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4).substr($str, -4);
    }

    protected static function resolveReference(array $config, mixed $value): string
    {
        if ($value === null) {
            return '(none)';
        }

        if (isset($config['model'])) {
            $model = app($config['model'])->find($value);
            $nameField = $config['name_field'] ?? 'name';

            return $model ? $model->{$nameField} : "(ID: {$value})";
        }

        return (string) $value;
    }

    protected static function getSensitiveAuditDisplayName(Model $model): string
    {
        foreach (['employee_id', 'name', 'payroll_number'] as $field) {
            if (! empty($model->{$field})) {
                return $model->{$field};
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    protected static function buildSensitiveChangeDescription(Model $model, array $changes): string
    {
        $modelName = class_basename($model);
        $displayName = static::getSensitiveAuditDisplayName($model);
        $fieldLabels = collect($changes)->pluck('label')->implode(', ');

        return "Sensitive field(s) changed on {$modelName} '{$displayName}': {$fieldLabels}";
    }
}
