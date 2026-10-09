<?php

namespace App\Http\Controllers;

use App\Enums\BankFeedConnectionStatus;
use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\BankFeedLine;
use App\Services\BankFeeds\BankFeedException;
use App\Services\BankFeeds\BankFeedLimit;
use App\Services\BankFeeds\BankFeedLinker;
use App\Services\BankFeeds\BankFeedProviders;
use App\Services\BankFeeds\BankFeedSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bank feeds page, linking and disconnecting a bank account (session 17).
 * Viewing needs "view banks"; linking, syncing and disconnecting need "edit banks".
 */
class BankFeedController extends Controller
{
    public function __construct(
        private BankFeedProviders $providers,
        private BankFeedLinker $linker,
        private BankFeedLimit $limit,
    ) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $connections = BankFeedConnection::with('bank')
            ->whereIn('status', [...BankFeedConnectionStatus::counted(), BankFeedConnectionStatus::Unlinked->value])
            ->orderByRaw("case when status = 'unlinked' then 1 else 0 end")->orderBy('id')->get();

        $toReview = BankFeedLine::toReview()->selectRaw('connection_id, count(*) as n')->groupBy('connection_id')->pluck('n', 'connection_id');

        return view('bank-feeds.index', [
            'connections' => $connections,
            'toReview' => $toReview,
            'live' => $this->providers->isLive(),
            'limitSummary' => $this->limit->summary($tenant),
            'limitReached' => $this->limit->reached($tenant),
        ]);
    }

    public function lines(Request $request)
    {
        return view('bank-feeds.lines', ['live' => $this->providers->isLive()]);
    }

    /** Pick which MyBooks bank account the feed will fill. */
    public function connect(Request $request)
    {
        $tenant = $request->user()->tenant;
        $taken = BankFeedConnection::counted()->pluck('bank_id');
        $banks = Bank::where('is_active', true)->where('currency', 'NGN')->whereNotIn('id', $taken)->orderBy('name')->get(['id', 'name', 'bank_name']);

        return view('bank-feeds.connect', [
            'banks' => $banks,
            'selected' => $request->integer('bank') ?: null,
            'live' => $this->providers->isLive(),
            'limitSummary' => $this->limit->summary($tenant),
            'limitReached' => $this->limit->reached($tenant),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'new_bank_name' => ['nullable', 'string', 'max:100', 'required_without:bank_id'],
        ], ['new_bank_name.required_without' => 'Choose a bank account, or give the new one a name.']);

        try {
            $bank = ! empty($data['bank_id']) ? Bank::findOrFail($data['bank_id']) : null;
            $url = $this->linker->start($request->user()->tenant, $request->user(), $bank, $data['new_bank_name'] ?? null);
        } catch (BankFeedException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->toBank($url);
    }

    /**
     * The page sends forms only to this site (Content-Security-Policy), so
     * the hop to Mono's page is a plain link on a small page of our own.
     */
    private function toBank(string $url): RedirectResponse
    {
        return redirect()->route('bank-feeds.go')->with('bank_link_url', $url);
    }

    /** "Taking you to your bank": opens the link kept from the step before. */
    public function go(Request $request): View|RedirectResponse
    {
        $url = $request->session()->get('bank_link_url');
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            return redirect()->route('bank-feeds.index')->with('error', 'That link has expired. Please start again.');
        }
        // Kept one more request so a refresh of this page still works.
        $request->session()->reflash();

        return view('bank-feeds.go', ['url' => $url]);
    }

    /** The customer is back from their bank (Mono sends them here). */
    public function callback(Request $request): RedirectResponse
    {
        $ref = (string) $request->query('ref');
        $connection = BankFeedConnection::where('link_ref', $ref)->first() ?? abort(404);

        $code = (string) ($request->query('code') ?? '');
        if ($code === '' && ! $connection->provider_account_id) {
            // Linked by Mono's own notice meanwhile, or the customer closed the window.
            $connection->refresh();
            if ($connection->status === BankFeedConnectionStatus::Pending->value) {
                return redirect()->route('bank-feeds.index')->with('error', 'The bank was not linked. You can try again whenever you like.');
            }
        }

        try {
            $done = $this->linker->complete($ref, $code !== '' ? $code : null);
        } catch (BankFeedException $e) {
            return redirect()->route('bank-feeds.index')->with('error', $e->getMessage());
        }

        $new = $done?->lines()->count() ?? 0;

        return redirect()->route('bank-feeds.index')->with('success', $done && $done->isActive()
            ? "{$done->title()} is connected. {$new} ".($new === 1 ? 'transaction is' : 'transactions are').' now in MyBooks to review.'
            : 'The bank was not linked.');
    }

    public function sync(BankFeedConnection $connection, BankFeedSync $sync): RedirectResponse
    {
        if (! $connection->isActive()) {
            return back()->with('error', 'This account is not connected.');
        }
        $provider = $this->providers->for($connection->provider);

        // Mono allows a live refresh every few minutes; in between we just read what it has.
        $gap = (int) config('mybooks.bank_feeds.sync_now_minutes', 5);
        $asked = false;
        if ($provider->isLive() && (! $connection->last_sync_requested_at || $connection->last_sync_requested_at->lt(now()->subMinutes($gap)))) {
            try {
                $provider->requestSync($connection->provider_account_id);
                $connection->forceFill(['last_sync_requested_at' => now()])->save();
                $asked = true;
            } catch (BankFeedException) {
                // still read what the bank already gave us
            }
        }

        $result = $sync->pull($connection);
        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['new'] > 0
            ? "{$result['new']} new ".($result['new'] === 1 ? 'transaction' : 'transactions').' found.'
            : 'Nothing new yet.'.($asked ? ' We asked the bank for the latest; new transactions appear in a few minutes.' : ''));
    }

    public function reconnect(Request $request, BankFeedConnection $connection): RedirectResponse
    {
        try {
            $url = $this->linker->startReauthorisation($connection, $request->user());
        } catch (BankFeedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->toBank($url);
    }

    public function disconnect(BankFeedConnection $connection): RedirectResponse
    {
        $this->linker->disconnect($connection);

        return redirect()->route('bank-feeds.index')->with('success', "{$connection->title()} is disconnected. The lines already read are kept.");
    }
}
