<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Customer;
use App\Models\Vendor;
use App\Services\Statements\Statement;
use App\Services\Statements\StatementBuilder;
use App\Services\Statements\StatementMailer;
use App\Services\Statements\StatementPdf;
use App\Services\Statements\Subledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Customer and supplier statements: on screen, print, PDF, email and bulk
 * email (session 10). The figures come from StatementBuilder.
 */
class StatementController extends Controller
{
    public function __construct(
        private StatementBuilder $builder,
        private StatementMailer $mailer,
        private StatementPdf $pdf,
    ) {}

    public function customer(Request $request, Customer $customer): View
    {
        return $this->page($request, $customer);
    }

    public function vendor(Request $request, Vendor $vendor): View
    {
        return $this->page($request, $vendor);
    }

    /** Reports > Customer Statement: pick a customer, then the same page. */
    public function pickCustomer(Request $request): View
    {
        $request->validate(['customer_id' => 'nullable|integer']);
        $customer = $request->filled('customer_id')
            ? Customer::where('tenant_id', auth()->user()->tenant_id)->findOrFail($request->integer('customer_id'))
            : null;

        return $this->page($request, $customer, Subledger::CUSTOMERS);
    }

    public function pickVendor(Request $request): View
    {
        $request->validate(['vendor_id' => 'nullable|integer']);
        $vendor = $request->filled('vendor_id')
            ? Vendor::where('tenant_id', auth()->user()->tenant_id)->findOrFail($request->integer('vendor_id'))
            : null;

        return $this->page($request, $vendor, Subledger::SUPPLIERS);
    }

    public function customerPrint(Request $request, Customer $customer): View
    {
        return $this->printView($request, $customer);
    }

    public function vendorPrint(Request $request, Vendor $vendor): View
    {
        return $this->printView($request, $vendor);
    }

    public function customerPdf(Request $request, Customer $customer): Response
    {
        return $this->download($request, $customer);
    }

    public function vendorPdf(Request $request, Vendor $vendor): Response
    {
        return $this->download($request, $vendor);
    }

    public function customerEmail(Request $request, Customer $customer): RedirectResponse
    {
        return $this->email($request, $customer);
    }

    public function vendorEmail(Request $request, Vendor $vendor): RedirectResponse
    {
        return $this->email($request, $vendor);
    }

    public function sendCustomers(Request $request): RedirectResponse
    {
        return $this->sendMany($request, Subledger::CUSTOMERS);
    }

    public function sendVendors(Request $request): RedirectResponse
    {
        return $this->sendMany($request, Subledger::SUPPLIERS);
    }

    // ---- helpers ----------------------------------------------------------

    /** @return array{0: string, 1: ?string, 2: string} type, from, to */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'type' => 'nullable|in:'.Statement::ACTIVITY.','.Statement::OPEN_ITEMS,
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);
        $type = $data['type'] ?? Statement::ACTIVITY;
        $to = $data['to'] ?? now()->toDateString();
        $from = $type === Statement::ACTIVITY ? ($data['from'] ?? now()->startOfYear()->toDateString()) : null;

        return [$type, $from, $to];
    }

    private function page(Request $request, Customer|Vendor|null $party, ?string $side = null): View
    {
        $side ??= $party instanceof Vendor ? Subledger::SUPPLIERS : Subledger::CUSTOMERS;
        [$type, $from, $to] = $this->period($request);
        $statement = $party ? $this->builder->build($party, $type, $from, $to) : null;
        $tenant = auth()->user()->tenant;
        $period = StatementMailer::periodText($type, $statement->from ?? $from, $statement->to ?? $to);

        return view('statements.show', [
            'statement' => $statement,
            'party' => $party,
            'side' => $side,
            'type' => $type,
            'from' => $statement->from ?? $from ?? now()->startOfYear()->toDateString(),
            'to' => $statement->to ?? $to,
            'tenant' => $tenant,
            // The reports version has a customer / supplier picker.
            'pickList' => $request->routeIs('reports.*')
                ? ($side === Subledger::SUPPLIERS ? Vendor::query() : Customer::query())->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name', 'company_name'])
                : null,
            'features' => EnsureFeatureEnabled::enabled('statements'),
            'emailSubject' => $statement ? $this->mailer->fill($this->mailer->defaultSubject($side), $statement->party, $period, $statement->closing, $tenant) : '',
            'emailMessage' => $statement ? $this->mailer->fill($this->mailer->defaultMessage($side), $statement->party, $period, $statement->closing, $tenant) : '',
        ]);
    }

    private function printView(Request $request, Customer|Vendor $party): View
    {
        [$type, $from, $to] = $this->period($request);
        $tenant = auth()->user()->tenant;

        return view('statements.document', [
            'statement' => $this->builder->build($party, $type, $from, $to),
            'tenant' => $tenant,
            'logo' => StatementPdf::logo($tenant),
        ]);
    }

    private function download(Request $request, Customer|Vendor $party): Response
    {
        [$type, $from, $to] = $this->period($request);
        $statement = $this->builder->build($party, $type, $from, $to);

        return response($this->pdf->render($statement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$statement->fileName().'"',
        ]);
    }

    private function email(Request $request, Customer|Vendor $party): RedirectResponse
    {
        [$type, $from, $to] = $this->period($request);
        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ]);
        $who = $party instanceof Vendor ? 'supplier' : 'customer';
        if (! $party->email) {
            return back()->with('error', "This {$who} has no email address. Add one on their page, or download the PDF and send it another way.");
        }

        $this->mailer->send($party, $type, $from, $to, $data['subject'], $data['message']);

        return back()->with('success', "The statement is being emailed to {$party->email} with the PDF attached.");
    }

    private function sendMany(Request $request, string $side): RedirectResponse
    {
        [$type, $from, $to] = $this->period($request);
        $data = $request->validate([
            'scope' => 'required|in:selected,balance',
            'ids' => 'required_if:scope,selected|array',
            'ids.*' => 'integer',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ], ['ids.required_if' => 'Tick at least one in the list first, or choose "everyone with a balance".']);

        $result = $this->mailer->sendMany(auth()->user()->tenant_id, $side,
            $data['scope'] === 'selected' ? $data['ids'] : null, $type, $from, $to, $data['subject'], $data['message']);

        $who = $side === Subledger::SUPPLIERS ? 'supplier' : 'customer';
        $text = $result['sent'] > 0
            ? "Statements are being emailed to {$result['sent']} ".str($who)->plural($result['sent']).'.'
            : 'No statements were sent.';
        if ($result['skipped']) {
            $n = count($result['skipped']);
            $text .= " Skipped {$n} with no email address: ".implode(', ', array_slice($result['skipped'], 0, 10)).($n > 10 ? ' and others' : '').'.';
        }

        return redirect()->route($side === Subledger::SUPPLIERS ? 'vendors.index' : 'customers.index')
            ->with($result['sent'] > 0 ? 'success' : 'error', $text);
    }
}
