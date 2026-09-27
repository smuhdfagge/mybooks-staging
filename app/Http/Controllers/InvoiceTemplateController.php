<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;

class InvoiceTemplateController extends Controller
{
    public function index()
    {
        $templates = InvoiceTemplate::orderByDesc('is_default')->orderBy('name')->get();

        // Seed defaults if no templates exist yet
        if ($templates->isEmpty()) {
            InvoiceTemplate::seedDefaultTemplates(auth()->user()->tenant_id);
            $templates = InvoiceTemplate::orderByDesc('is_default')->orderBy('name')->get();
        }

        return view('settings.invoice-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('settings.invoice-templates.create');
    }

    public function edit(InvoiceTemplate $invoiceTemplate)
    {
        return view('settings.invoice-templates.edit', ['template' => $invoiceTemplate]);
    }

    public function setDefault(InvoiceTemplate $invoiceTemplate)
    {
        // Unset existing default
        InvoiceTemplate::where('is_default', true)->update(['is_default' => false]);

        // Set new default
        $invoiceTemplate->update(['is_default' => true]);

        // Update tenant
        auth()->user()->tenant->update(['invoice_template_id' => $invoiceTemplate->id]);

        return redirect()->route('settings.invoice-templates.index')
            ->with('success', "'{$invoiceTemplate->name}' set as the default invoice template.");
    }

    public function destroy(InvoiceTemplate $invoiceTemplate)
    {
        if ($invoiceTemplate->is_default) {
            return redirect()->back()->with('error', 'Cannot delete the default template.');
        }

        $invoiceTemplate->delete();

        return redirect()->route('settings.invoice-templates.index')
            ->with('success', 'Template deleted successfully.');
    }
}
