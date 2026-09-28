<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InvoiceTemplate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    public static function booted(): void
    {
        static::creating(function (InvoiceTemplate $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function getDefaultSettings(): array
    {
        return [
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1F2937',
            'accent_color' => '#059669',
            'font_family' => 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif',
            'font_size' => '13',
            'header_bg_color' => '#FFFFFF',
            'header_text_color' => '#1F2937',
            'table_header_bg' => '#F9FAFB',
            'table_header_text' => '#6B7280',
            'table_border_color' => '#E5E7EB',
            'footer_bg_color' => '#F9FAFB',
            'footer_text_color' => '#6B7280',
            'show_logo' => true,
            'show_status_badge' => true,
            'show_tax_column' => true,
            'show_payment_info' => true,
            'show_notes' => true,
            'show_terms' => true,
            'show_footer' => true,
            'footer_text' => 'Thank you for your business!',
            'layout' => 'classic',
            'border_style' => 'solid',
            'border_width' => '3',
        ];
    }

    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];

        return $settings[$key] ?? self::getDefaultSettings()[$key] ?? $default;
    }

    public static function getLayoutOptions(): array
    {
        return [
            'classic' => 'Classic',
            'modern' => 'Modern',
            'minimal' => 'Minimal',
            'compact' => 'Compact',
        ];
    }

    public static function getFontOptions(): array
    {
        return [
            'Segoe UI, Tahoma, Geneva, Verdana, sans-serif' => 'Segoe UI',
            'Arial, Helvetica, sans-serif' => 'Arial',
            'Georgia, Times New Roman, serif' => 'Georgia',
            'Courier New, monospace' => 'Courier New',
            'Trebuchet MS, sans-serif' => 'Trebuchet MS',
            'Palatino Linotype, Book Antiqua, serif' => 'Palatino',
        ];
    }

    public static function seedDefaultTemplates(int $tenantId): InvoiceTemplate
    {
        $classic = self::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'classic'],
            [
                'name' => 'Classic',
                'is_default' => true,
                'settings' => self::getDefaultSettings(),
            ]
        );

        self::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'modern'],
            [
                'name' => 'Modern',
                'is_default' => false,
                'settings' => array_merge(self::getDefaultSettings(), [
                    'layout' => 'modern',
                    'primary_color' => '#6366F1',
                    'accent_color' => '#10B981',
                    'border_style' => 'none',
                    'border_width' => '0',
                    'header_bg_color' => '#6366F1',
                    'header_text_color' => '#FFFFFF',
                ]),
            ]
        );

        self::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'minimal'],
            [
                'name' => 'Minimal',
                'is_default' => false,
                'settings' => array_merge(self::getDefaultSettings(), [
                    'layout' => 'minimal',
                    'primary_color' => '#111827',
                    'secondary_color' => '#374151',
                    'accent_color' => '#111827',
                    'border_style' => 'none',
                    'border_width' => '0',
                    'table_header_bg' => '#FFFFFF',
                    'footer_bg_color' => '#FFFFFF',
                ]),
            ]
        );

        self::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'compact'],
            [
                'name' => 'Compact',
                'is_default' => false,
                'settings' => array_merge(self::getDefaultSettings(), [
                    'layout' => 'compact',
                    'primary_color' => '#0891B2',
                    'font_size' => '11',
                    'border_width' => '2',
                ]),
            ]
        );

        return $classic;
    }
}
