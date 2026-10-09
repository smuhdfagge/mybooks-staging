<?php

namespace App\Http\Controllers;

use App\Models\CustomerMessage;
use App\Models\MessageSetting;
use App\Services\Messaging\CustomerMessenger;
use App\Services\Messaging\MessageAllowance;
use App\Services\Messaging\MessageTemplates;
use App\Services\Messaging\MessagingDrivers;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings > SMS & WhatsApp (session 16): which messages go by SMS or
 * WhatsApp, the SMS wording with a live preview, a test message, the
 * month's usage, and the list of messages sent.
 */
class MessagingSettingsController extends Controller
{
    public function __construct(
        private MessageAllowance $allowance,
        private MessagingDrivers $drivers,
        private MessageTemplates $templates,
    ) {}

    public function show(Request $request)
    {
        $tenant = $request->user()->tenant;
        $settings = MessageSetting::forTenant($tenant->id);

        $usage = [];
        foreach (MessageSetting::CHANNELS as $channel) {
            $usage[$channel] = [
                'used' => $this->allowance->used($tenant->id, $channel),
                'limit' => $this->allowance->limit($tenant, $channel),
                'summary' => $this->allowance->summary($tenant, $channel),
                'live' => $this->drivers->isLive($channel),
            ];
        }

        $sample = $this->templates->sample($tenant);
        // An overdue example needs a due date in the past.
        $overdueSample = ['due_date' => now()->subDays(3)->format('j M Y')] + $sample;
        $whatsappPreview = [];
        foreach (MessageSetting::TYPES as $type) {
            $whatsappPreview[$type] = $this->templates->whatsappPreview($type, $this->templates->whatsappParams($type, $type === 'overdue' ? $overdueSample : $sample));
        }

        return view('settings.messaging.show', [
            'settings' => $settings,
            'usage' => $usage,
            'sample' => $sample,
            'overdueSample' => $overdueSample,
            'whatsappPreview' => $whatsappPreview,
            'placeholders' => MessageTemplates::PLACEHOLDERS,
            'defaults' => MessageTemplates::DEFAULTS,
            'recent' => CustomerMessage::with(['customer', 'invoice'])->latest('id')->limit(5)->get(),
            'canSetUp' => $usage['sms']['live'] || $usage['whatsapp']['live'] || ! app()->isProduction(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (MessageSetting::TYPES as $type) {
            $rules[$type.'_sms'] = ['boolean'];
            $rules[$type.'_whatsapp'] = ['boolean'];
            $rules[$type.'_text'] = ['nullable', 'string', 'max:459', function (string $attribute, mixed $value, \Closure $fail) {
                $text = trim((string) $value);
                if ($text === '') {
                    return;
                }
                // The sender ID is MyBooks's, so the customer must see who it's from.
                if (! str_starts_with($text, '{business}')) {
                    $fail('Start the message with {business}, so your customer knows who it is from.');
                }
                preg_match_all('/\{[a-z_]+\}/', $text, $found);
                $unknown = array_diff($found[0], array_keys(MessageTemplates::PLACEHOLDERS));
                if ($unknown) {
                    $fail('Unknown placeholder '.implode(', ', $unknown).'. Use only the ones listed.');
                }
            }];
        }
        $validated = $request->validate($rules);

        $data = [];
        foreach (MessageSetting::TYPES as $type) {
            $data[$type.'_sms'] = $request->boolean($type.'_sms');
            $data[$type.'_whatsapp'] = $request->boolean($type.'_whatsapp');
            $text = trim((string) ($validated[$type.'_text'] ?? ''));
            // Saving the default wording unchanged keeps following the default.
            $data[$type.'_text'] = $text === '' || $text === MessageTemplates::DEFAULTS[$type] ? null : $text;
        }

        MessageSetting::forTenant($request->user()->tenant_id)->update($data);

        return redirect()->route('settings.messaging')->with('success', 'SMS and WhatsApp settings saved.');
    }

    public function test(Request $request, CustomerMessenger $messenger)
    {
        $validated = $request->validate([
            'test_phone' => ['required', 'string', 'max:30'],
            'test_channel' => ['required', Rule::in(MessageSetting::CHANNELS)],
        ]);

        $result = $messenger->sendTest($request->user()->tenant, $validated['test_phone'], $validated['test_channel'], $request->user()->id);

        if (is_string($result)) {
            return redirect()->route('settings.messaging')->withInput()->with('error', CustomerMessenger::REASONS[$result] ?? 'The test message could not be sent.');
        }

        $result->refresh();
        $label = $result->channelLabel();

        return redirect()->route('settings.messaging')->with('success', $result->status === 'failed'
            ? "The test {$label} failed: {$result->error}"
            : "Test {$label} sent to ".PhoneNumber::mask($result->to).'.');
    }

    public function messages(Request $request)
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(MessageSetting::CHANNELS)],
            'status' => ['nullable', Rule::in(['queued', 'sending', 'sent', 'delivered', 'failed'])],
        ]);

        $messages = CustomerMessage::with(['customer', 'invoice'])
            ->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('channel', $channel))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('settings.messaging.messages', compact('messages', 'filters'));
    }
}
