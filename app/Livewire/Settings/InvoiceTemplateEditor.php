<?php

namespace App\Livewire\Settings;

use App\Models\InvoiceTemplate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use App\Livewire\Concerns\ChecksPermissions;

class InvoiceTemplateEditor extends Component
{
    use ChecksPermissions;

    public ?InvoiceTemplate $template = null;
    public string $name = '';
    public array $settings = [];
    public bool $isNew = false;

    // Settings properties bound to form
    public string $primary_color = '#3B82F6';
    public string $secondary_color = '#1F2937';
    public string $accent_color = '#059669';
    public string $font_family = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';
    public string $font_size = '13';
    public string $header_bg_color = '#FFFFFF';
    public string $header_text_color = '#1F2937';
    public string $table_header_bg = '#F9FAFB';
    public string $table_header_text = '#6B7280';
    public string $table_border_color = '#E5E7EB';
    public string $footer_bg_color = '#F9FAFB';
    public string $footer_text_color = '#6B7280';
    public bool $show_logo = true;
    public bool $show_status_badge = true;
    public bool $show_tax_column = true;
    public bool $show_payment_info = true;
    public bool $show_notes = true;
    public bool $show_terms = true;
    public bool $show_footer = true;
    public string $footer_text = 'Thank you for your business!';
    public string $layout = 'classic';
    public string $border_style = 'solid';
    public string $border_width = '3';

    /**
     * Colours must be #RRGGBB and the font must come from the list offered
     * in the editor, because both are written into the invoice's CSS (L13).
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_family' => ['required', Rule::in(array_keys(InvoiceTemplate::getFontOptions()))],
            'font_size' => 'required|numeric|min:8|max:20',
            'header_bg_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'header_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'table_header_bg' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'table_header_text' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'table_border_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_bg_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_text' => 'nullable|string|max:255',
            'layout' => 'required|string|in:classic,modern,minimal,compact',
            'border_style' => 'required|string|in:solid,dashed,dotted,none',
            'border_width' => 'required|numeric|min:0|max:10',
        ];
    }

    public function mount(?InvoiceTemplate $template = null)
    {
        if ($template && $template->exists) {
            $this->template = $template;
            $this->name = $template->name;
            $this->isNew = false;
            $this->loadSettingsFromTemplate($template);
        } else {
            $this->isNew = true;
            $this->loadDefaultSettings();
        }
    }

    private function loadSettingsFromTemplate(InvoiceTemplate $template): void
    {
        $defaults = InvoiceTemplate::getDefaultSettings();
        $settings = array_merge($defaults, $template->settings ?? []);

        foreach ($settings as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    private function loadDefaultSettings(): void
    {
        $defaults = InvoiceTemplate::getDefaultSettings();
        foreach ($defaults as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        $this->name = '';
    }

    public function getSettingsArray(): array
    {
        return [
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'font_family' => $this->font_family,
            'font_size' => $this->font_size,
            'header_bg_color' => $this->header_bg_color,
            'header_text_color' => $this->header_text_color,
            'table_header_bg' => $this->table_header_bg,
            'table_header_text' => $this->table_header_text,
            'table_border_color' => $this->table_border_color,
            'footer_bg_color' => $this->footer_bg_color,
            'footer_text_color' => $this->footer_text_color,
            'show_logo' => $this->show_logo,
            'show_status_badge' => $this->show_status_badge,
            'show_tax_column' => $this->show_tax_column,
            'show_payment_info' => $this->show_payment_info,
            'show_notes' => $this->show_notes,
            'show_terms' => $this->show_terms,
            'show_footer' => $this->show_footer,
            'footer_text' => $this->footer_text,
            'layout' => $this->layout,
            'border_style' => $this->border_style,
            'border_width' => $this->border_width,
        ];
    }

    public function save()
    {
        $this->requirePermission('edit settings');

        $this->validate();

        $tenantId = auth()->user()->tenant_id;
        $settings = $this->getSettingsArray();

        if ($this->isNew) {
            $this->template = InvoiceTemplate::create([
                'tenant_id' => $tenantId,
                'name' => $this->name,
                'settings' => $settings,
            ]);
            $this->isNew = false;

            session()->flash('success', 'Invoice template created successfully.');
            return redirect()->route('settings.invoice-templates.edit', $this->template);
        }

        $this->template->update([
            'name' => $this->name,
            'settings' => $settings,
        ]);

        session()->flash('success', 'Invoice template updated successfully.');
    }

    public function setAsDefault()
    {
        $this->requirePermission('edit settings');

        if (!$this->template) {
            return;
        }

        $tenant = auth()->user()->tenant;

        // Unset any existing default
        InvoiceTemplate::where('is_default', true)->update(['is_default' => false]);

        // Set this as default
        $this->template->update(['is_default' => true]);

        // Update tenant's active template
        $tenant->update(['invoice_template_id' => $this->template->id]);

        session()->flash('success', "'{$this->template->name}' set as the default invoice template.");
    }

    public function resetToDefaults()
    {
        $this->loadDefaultSettings();
    }

    public function updated($property)
    {
        // Emit event for live preview update
        $this->dispatch('template-settings-updated', settings: $this->getSettingsArray());
    }

    public function render()
    {
        return view('livewire.settings.invoice-template-editor', [
            'fontOptions' => InvoiceTemplate::getFontOptions(),
            'layoutOptions' => InvoiceTemplate::getLayoutOptions(),
        ]);
    }
}
