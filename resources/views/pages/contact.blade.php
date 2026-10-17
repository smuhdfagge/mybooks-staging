<x-public.layout title="Contact us" description="Contact MyBooks by phone, email or the contact form, or book a demo.">

        <!-- Hero Section -->
        <section class="pt-14 pb-12 lg:pt-20 px-4 sm:px-6 lg:px-8 bg-brand-50 dark:bg-gray-900">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="font-serif text-4xl lg:text-5xl font-semibold text-gray-900 dark:text-white mb-4">
                    Contact us
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                    Questions about MyBooks, help with your account, or a demo: send us a message or call.
                </p>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-6xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12">
                    
                    <!-- Contact Information -->
                    <div>
                        <h2 class="font-serif text-2xl font-semibold text-gray-900 dark:text-white mb-6">Contact details</h2>
                        <p class="text-gray-600 dark:text-gray-300 mb-8">
                            Use the form, or reach us by email or phone.
                        </p>

                        <div class="mb-8 rounded-xl border border-accent-200 bg-accent-50 p-5 dark:border-accent-800 dark:bg-accent-900/20">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Book a demo</h3>
                            <p class="mt-1 text-gray-700 dark:text-gray-300">Choose "Book a demo" as the subject and tell us a good day and time. We will get back to you to arrange it.</p>
                            <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="mt-3 inline-block font-semibold text-brand-700 underline dark:text-brand-300">Book a demo</a>
                        </div>

                        <div class="space-y-6">
                            <!-- Email -->
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-12 h-12 bg-brand-100 dark:bg-brand-900/30 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">Email</h3>
                                    <p class="text-gray-600 dark:text-gray-400">For questions and help with your account.</p>
                                    <a href="mailto:support@my-books.cloud" class="text-brand-600 dark:text-brand-300 hover:underline">support@my-books.cloud</a>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">Phone</h3>
                                    <p class="text-gray-600 dark:text-gray-400">Mon-Fri from 8am to 5pm.</p>
                                    <a href="tel:+2348023491938" class="text-brand-600 dark:text-brand-300 hover:underline">+2348023491938</a>
                                </div>
                            </div>

                            <!-- Office -->
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-12 h-12 bg-accent-100 dark:bg-accent-900/30 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-accent-700 dark:text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">Office</h3>
                                    <p class="text-gray-600 dark:text-gray-400">Our office in Kano.</p>
                                    <p class="text-gray-700 dark:text-gray-300">No 580 ando House, Wapa Fagge<br>Kano State 700213</p>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- Contact Form -->
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-8">
                        <h2 class="font-serif text-2xl font-semibold text-gray-900 dark:text-white mb-6">Send us a message</h2>
                        
                        @if(session('success'))
                            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                <p class="text-green-700 dark:text-green-400">{{ session('success') }}</p>
                            </div>
                        @endif

                        <form id="contact-form" action="{{ route('contact.submit') }}" method="POST" class="space-y-6 scroll-mt-24">
                            @csrf
                            <input type="hidden" name="_form_loaded_at" value="{{ now()->timestamp }}">

                            {{-- Honeypot: hidden from humans, bots will fill it --}}
                            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                                <label for="website">Leave this empty</label>
                                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- First Name -->
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First name</label>
                                    <input type="text" name="first_name" id="first_name" required
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                                        placeholder="Amina" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                                    @error('first_name')
                                        <p id="first_name-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Last Name -->
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last name</label>
                                    <input type="text" name="last_name" id="last_name" required
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                                        placeholder="Bello" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                                    @error('last_name')
                                        <p id="last_name-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                <input type="email" name="email" id="email" required
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                                    placeholder="amina@example.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                                @error('email')
                                    <p id="email-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone number <span class="text-gray-600 dark:text-gray-400">(optional)</span></label>
                                <input type="tel" name="phone" id="phone"
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                                    placeholder="0803 123 4567" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                                @error('phone')
                                    <p id="phone-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Subject -->
                            <div>
                                <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject</label>
                                <select name="subject" id="subject" required
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition" @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                                    @php($pickedSubject = old('subject', request()->query('subject')))
                                    <option value="">Select a subject</option>
                                    <option value="demo" @selected($pickedSubject === 'demo')>Book a demo</option>
                                    <option value="general" @selected($pickedSubject === 'general')>General question</option>
                                    <option value="support" @selected($pickedSubject === 'support')>Help with MyBooks</option>
                                    <option value="sales" @selected($pickedSubject === 'sales')>Plans and prices</option>
                                    <option value="billing" @selected($pickedSubject === 'billing')>Billing and payment</option>
                                    <option value="feedback" @selected($pickedSubject === 'feedback')>Feedback</option>
                                    <option value="partnership" @selected($pickedSubject === 'partnership')>Working together</option>
                                    <option value="other" @selected($pickedSubject === 'other')>Other</option>
                                </select>
                                @error('subject')
                                    <p id="subject-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Message -->
                            <div>
                                <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</label>
                                <textarea name="message" id="message" rows="5" required
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition resize-none"
                                    placeholder="Tell us how we can help you..." @error('message') aria-invalid="true" aria-describedby="message-error" @enderror></textarea>
                                @error('message')
                                    <p id="message-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Submit Button -->
                            <button type="submit"
                                class="w-full px-6 py-4 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 focus:ring-4 focus:ring-brand-300 dark:focus:ring-brand-800 transition">
                                Send message
                            </button>

                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                                By submitting this form, you agree to our 
                                <a href="{{ route('privacy-policy') }}" class="text-brand-600 dark:text-brand-300 hover:underline">privacy policy</a>.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white mb-4">Questions people ask</h2>
                    <p class="text-gray-600 dark:text-gray-300">Not here? Send us a message above.</p>
                </div>

                <div class="space-y-4">
                    <details class="group rounded-xl bg-white shadow-sm dark:bg-gray-900">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-6 py-4 font-semibold text-gray-900 focus-ring dark:text-white [&::-webkit-details-marker]:hidden">
                            <span>How do I get started with MyBooks?</span>
                            <svg class="h-5 w-5 flex-shrink-0 text-gray-500 transition-transform group-open:rotate-180 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <p class="px-6 pb-5 text-gray-600 dark:text-gray-300">Choose a plan, create your account and pay through Paystack. A getting-started list on your dashboard then shows the first steps: your business details, bank accounts, and your first customers and items.</p>
                    </details>
                    <details class="group rounded-xl bg-white shadow-sm dark:bg-gray-900">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-6 py-4 font-semibold text-gray-900 focus-ring dark:text-white [&::-webkit-details-marker]:hidden">
                            <span>Is my data secure with MyBooks?</span>
                            <svg class="h-5 w-5 flex-shrink-0 text-gray-500 transition-transform group-open:rotate-180 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <p class="px-6 pb-5 text-gray-600 dark:text-gray-300">Your records travel over an encrypted connection (HTTPS), each business's records are kept apart, and every user can turn on two-step sign-in. We do not sell your data. The few outside services MyBooks uses to run, such as payments and email, are listed in our <a href="{{ route('privacy-policy') }}" class="font-medium text-brand-600 underline dark:text-brand-300">privacy policy</a>.</p>
                    </details>
                    <details class="group rounded-xl bg-white shadow-sm dark:bg-gray-900">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-6 py-4 font-semibold text-gray-900 focus-ring dark:text-white [&::-webkit-details-marker]:hidden">
                            <span>Can I bring in data from other accounting software?</span>
                            <svg class="h-5 w-5 flex-shrink-0 text-gray-500 transition-transform group-open:rotate-180 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <p class="px-6 pb-5 text-gray-600 dark:text-gray-300">Yes. Upload a spreadsheet of your customers, suppliers, items, chart of accounts, opening balances, invoices, bills, expenses, employees, journals or budgets. MyBooks shows you each column before it saves.</p>
                    </details>
                    <details class="group rounded-xl bg-white shadow-sm dark:bg-gray-900">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-6 py-4 font-semibold text-gray-900 focus-ring dark:text-white [&::-webkit-details-marker]:hidden">
                            <span>How can I get help?</span>
                            <svg class="h-5 w-5 flex-shrink-0 text-gray-500 transition-transform group-open:rotate-180 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <p class="px-6 pb-5 text-gray-600 dark:text-gray-300">By email or phone, Monday to Friday from 8am to 5pm. The details are at the top of this page.</p>
                    </details>
                </div>
            </div>
        </section>
</x-public.layout>
