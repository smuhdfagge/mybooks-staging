<?php

namespace App\Providers;

use App\Contracts\JournalServiceInterface;
use App\Events;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureAccountActive;
use App\Listeners;
use App\Models\CreditNoteRefund;
use App\Models\Expense;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\StatutoryRemittance;
use App\Models\User;
use App\Models\VendorCreditRefund;
use App\Services\Accounting\LockDates;
use App\Services\ActivityLogService;
use App\Services\BankFeeds\LineActions;
use App\Services\JournalService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register a per-request CSP nonce (cryptographically random).
        // Shared between SecurityHeaders middleware and Blade views.
        $this->app->singleton('csp-nonce', function () {
            return base64_encode(random_bytes(16));
        });

        $this->app->bind(JournalServiceInterface::class, JournalService::class);

        // Lock dates read once per request (session 11).
        $this->app->scoped(LockDates::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In local development, log a warning whenever a relation is
        // lazy-loaded, so N+1 queries are noticed early (P4). It only
        // logs; nothing is blocked.
        Model::preventLazyLoading($this->app->isLocal());
        Model::handleLazyLoadingViolationUsing(function ($model, string $relation) {
            Log::warning('Lazy loading '.get_class($model).'::'.$relation.' (possible N+1 query)');
        });

        // One way to show money in views (U8): @money($amount[, 'USD']) and @currencySymbol.
        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::format({$expression})); ?>");
        Blade::directive('currencySymbol', fn () => '<?php echo e(\\App\\Support\\Money::symbol()); ?>');

        // Configure password strength defaults (NIST 800-63B compliant)
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        // ── API Rate Limiters ─────────────────────────────────────
        // Every signed-in API route: reads 120/min and writes 30/min per user,
        // counted separately, so no route is left without a limit (I8).
        RateLimiter::for('api', function (Request $request) {
            $who = $request->user()?->id ?: $request->ip();

            return $request->isMethodSafe()
                ? Limit::perMinute(120)->by('read:'.$who)
                : Limit::perMinute(30)->by('write:'.$who);
        });

        // Reports read the whole ledger: 30/min per user (I8).
        RateLimiter::for('api-reports', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // API reads (GET): higher limit — 120 req/min per user
        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // API writes (POST/PUT/DELETE): lower limit — 30 req/min per user
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Sensitive operations (auth, password): 5 req/min per IP
        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Resending the verification email: per user, also capped per hour (S8)
        RateLimiter::for('verification-email', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(2)->by('minute:'.$key),
                Limit::perHour(6)->by('hour:'.$key),
            ];
        });

        // Export/backup: 10 req/min per user (expensive operations)
        RateLimiter::for('api-export', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Livewire component actions are sent to /livewire/update, not to the
        // page's route, so page middleware does not run on them unless it is
        // marked persistent. Keep deactivated users (H1) and tenants whose
        // subscription has ended (C1) out of pages they already have open.
        Livewire::addPersistentMiddleware([
            EnsureAccountActive::class,
            CheckSubscription::class,
        ]);

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-level logic
        Gate::before(function ($user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        // Log authentication events (only for regular users, not admin users)
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                ActivityLogService::logLogin($event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                ActivityLogService::logLogout($event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            ActivityLogService::logFailedLogin($event->credentials['email'] ?? 'unknown');
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            if ($event->user instanceof User) {
                ActivityLogService::logPasswordReset($event->user);
            }
        });

        // ── Domain Event → Listener mappings ─────────────────────
        // This is the only place listeners are registered: event discovery is
        // disabled in bootstrap/app.php. Add new listeners here.
        // Invoices
        Event::listen(Events\InvoiceSaved::class, Listeners\CreateInvoiceJournal::class);
        Event::listen(Events\InvoiceDeleting::class, Listeners\DeleteInvoiceJournal::class);

        // Bills
        Event::listen(Events\BillSaved::class, Listeners\CreateBillJournal::class);
        Event::listen(Events\BillDeleting::class, Listeners\DeleteBillJournal::class);

        // Sales Receipts
        Event::listen(Events\SalesReceiptSaved::class, Listeners\CreateSalesReceiptJournal::class);
        Event::listen(Events\SalesReceiptDeleting::class, Listeners\DeleteSalesReceiptJournal::class);

        // Payments Received
        Event::listen(Events\PaymentReceivedCreated::class, Listeners\HandlePaymentReceivedCreated::class);
        Event::listen(Events\PaymentReceivedUpdated::class, Listeners\HandlePaymentReceivedUpdated::class);
        Event::listen(Events\PaymentReceivedDeleting::class, Listeners\HandlePaymentReceivedDeleting::class);
        Event::listen(Events\PaymentReceivedDeleted::class, Listeners\HandlePaymentReceivedDeleted::class);

        // Payments Made
        Event::listen(Events\PaymentMadeCreated::class, Listeners\HandlePaymentMadeCreated::class);
        Event::listen(Events\PaymentMadeUpdated::class, Listeners\HandlePaymentMadeUpdated::class);
        Event::listen(Events\PaymentMadeDeleting::class, Listeners\HandlePaymentMadeDeleting::class);
        Event::listen(Events\PaymentMadeDeleted::class, Listeners\HandlePaymentMadeDeleted::class);

        // Expenses
        Event::listen(Events\ExpensePaid::class, Listeners\HandleExpensePaid::class);
        Event::listen(Events\ExpenseDeleting::class, Listeners\DeleteExpenseJournal::class);

        // Payroll
        Event::listen(Events\PayrollPaid::class, Listeners\HandlePayrollPaid::class);
        Event::listen(Events\PayrollDeleting::class, Listeners\DeletePayrollJournal::class);

        // Invoice Refunds
        Event::listen(Events\InvoiceRefundDeleting::class, Listeners\DeleteInvoiceRefundJournal::class);

        // Deleting a record that a bank feed line was matched to (or made from)
        // frees the line again (session 17).
        foreach ([PaymentReceived::class, PaymentMade::class, Expense::class, StatutoryRemittance::class, VendorCreditRefund::class, CreditNoteRefund::class] as $class) {
            $class::deleting(fn (Model $record) => app(LineActions::class)->releaseFor($record));
        }
    }
}
