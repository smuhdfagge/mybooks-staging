<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Notification Settings
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('settings.notifications.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Invoice Notifications -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Invoice & Payment Notifications
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure email notifications for invoices and payments.</p>

                            <div class="mt-6 space-y-4">
                                <!-- Send invoice on create -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_invoice_on_create" id="send_invoice_on_create" value="1"
                                            {{ $settings->send_invoice_on_create ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_invoice_on_create" class="font-medium text-gray-700 dark:text-gray-300">Auto-send invoice on creation</label>
                                        <p class="text-gray-500 dark:text-gray-400">Automatically email invoice to customer when created</p>
                                    </div>
                                </div>

                                <!-- Send payment confirmation -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_payment_confirmation" id="send_payment_confirmation" value="1"
                                            {{ $settings->send_payment_confirmation ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_payment_confirmation" class="font-medium text-gray-700 dark:text-gray-300">Send payment confirmations</label>
                                        <p class="text-gray-500 dark:text-gray-400">Email customers when payment is received</p>
                                    </div>
                                </div>

                                <!-- Send overdue reminders -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_overdue_reminders" id="send_overdue_reminders" value="1"
                                            {{ $settings->send_overdue_reminders ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_overdue_reminders" class="font-medium text-gray-700 dark:text-gray-300">Send overdue reminders</label>
                                        <p class="text-gray-500 dark:text-gray-400">Email customers about overdue invoices</p>
                                    </div>
                                </div>

                                <div class="ml-7">
                                    <label for="overdue_reminder_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remind every</label>
                                    <div class="mt-1 flex items-center">
                                        <input type="number" name="overdue_reminder_days" id="overdue_reminder_days" min="1" max="30"
                                            value="{{ old('overdue_reminder_days', $settings->overdue_reminder_days) }}"
                                            class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">days</span>
                                    </div>
                                </div>

                                <!-- Send payment reminders -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_payment_reminders" id="send_payment_reminders" value="1"
                                            {{ $settings->send_payment_reminders ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_payment_reminders" class="font-medium text-gray-700 dark:text-gray-300">Send upcoming payment reminders</label>
                                        <p class="text-gray-500 dark:text-gray-400">Email customers before invoice due date</p>
                                    </div>
                                </div>

                                <div class="ml-7">
                                    <label for="payment_reminder_days_before" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remind</label>
                                    <div class="mt-1 flex items-center">
                                        <input type="number" name="payment_reminder_days_before" id="payment_reminder_days_before" min="1" max="14"
                                            value="{{ old('payment_reminder_days_before', $settings->payment_reminder_days_before) }}"
                                            class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">days before due date</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bill Notifications -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Bill & Expense Notifications
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure internal notifications for bills and expenses.</p>

                            <div class="mt-6 space-y-4">
                                <!-- Send bill due reminders -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_bill_due_reminders" id="send_bill_due_reminders" value="1"
                                            {{ $settings->send_bill_due_reminders ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_bill_due_reminders" class="font-medium text-gray-700 dark:text-gray-300">Send bill payment reminders</label>
                                        <p class="text-gray-500 dark:text-gray-400">Notify team members about upcoming bill payments</p>
                                    </div>
                                </div>

                                <div class="ml-7">
                                    <label for="bill_reminder_days_before" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remind</label>
                                    <div class="mt-1 flex items-center">
                                        <input type="number" name="bill_reminder_days_before" id="bill_reminder_days_before" min="1" max="14"
                                            value="{{ old('bill_reminder_days_before', $settings->bill_reminder_days_before) }}"
                                            class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">days before due date</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Notifications -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                Inventory Notifications
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure low stock alerts.</p>

                            <div class="mt-6 space-y-4">
                                <!-- Send low stock alerts -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_low_stock_alerts" id="send_low_stock_alerts" value="1"
                                            {{ $settings->send_low_stock_alerts ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_low_stock_alerts" class="font-medium text-gray-700 dark:text-gray-300">Send low stock alerts</label>
                                        <p class="text-gray-500 dark:text-gray-400">Notify when items fall below reorder level</p>
                                    </div>
                                </div>

                                <div class="ml-7">
                                    <label for="low_stock_alert_frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alert frequency</label>
                                    <select name="low_stock_alert_frequency" id="low_stock_alert_frequency"
                                        class="mt-1 block w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="daily" {{ $settings->low_stock_alert_frequency === 'daily' ? 'selected' : '' }}>Daily</option>
                                        <option value="weekly" {{ $settings->low_stock_alert_frequency === 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- HR Notifications -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                HR & Payroll Notifications
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure HR-related notifications.</p>

                            <div class="mt-6 space-y-4">
                                <!-- Send payroll notifications -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_payroll_notifications" id="send_payroll_notifications" value="1"
                                            {{ $settings->send_payroll_notifications ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_payroll_notifications" class="font-medium text-gray-700 dark:text-gray-300">Send payroll notifications</label>
                                        <p class="text-gray-500 dark:text-gray-400">Email employees when payroll is approved</p>
                                    </div>
                                </div>

                                <!-- Send leave notifications -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="send_leave_notifications" id="send_leave_notifications" value="1"
                                            {{ $settings->send_leave_notifications ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_leave_notifications" class="font-medium text-gray-700 dark:text-gray-300">Send leave request notifications</label>
                                        <p class="text-gray-500 dark:text-gray-400">Notify about leave requests and approvals</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Email Settings
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Customize sender information for outgoing emails.</p>

                            <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                <div>
                                    <label for="email_from_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Name</label>
                                    <input type="text" name="email_from_name" id="email_from_name"
                                        value="{{ old('email_from_name', $settings->email_from_name) }}"
                                        placeholder="{{ auth()->user()->tenant->name }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to use company name</p>
                                </div>

                                <div>
                                    <label for="email_from_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Email Address</label>
                                    <input type="email" name="email_from_address" id="email_from_address"
                                        value="{{ old('email_from_address', $settings->email_from_address) }}"
                                        placeholder="{{ config('mail.from.address') }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to use system default</p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="email_reply_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reply-To Address</label>
                                    <input type="email" name="email_reply_to" id="email_reply_to"
                                        value="{{ old('email_reply_to', $settings->email_reply_to) }}"
                                        placeholder="{{ auth()->user()->tenant->email }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Customer replies will be sent to this address</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            Save Settings
                        </button>
                    </div>
                </div>
            </form>

            <!-- Test Email Section -->
            <div class="mt-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Test Email Configuration</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Send a test email to verify your configuration is working correctly.</p>

                    <form action="{{ route('settings.notifications.test') }}" method="POST" class="mt-5">
                        @csrf
                        <div class="flex items-end gap-4">
                            <div class="flex-1">
                                <label for="test_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                                <input type="email" name="test_email" id="test_email"
                                    value="{{ auth()->user()->email }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required>
                            </div>
                            <button type="submit"
                                class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 py-2 px-4 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Send Test Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
