<x-guest-layout>
    <div class="text-center">
        <!-- Email Icon -->
        <div class="mx-auto w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-white mb-2">Verify Your Email</h2>
        
        <p class="text-gray-400 mb-6">
            Thanks for signing up! We've sent a verification link to your email address. 
            Please click the link in the email to verify your account and access your dashboard.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-6 p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                <div class="flex items-center justify-center gap-2 text-green-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="font-medium">A new verification link has been sent to your email!</span>
                </div>
            </div>
        @endif

        <div class="bg-gray-800/50 rounded-xl p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">Didn't receive the email?</h3>
            <ul class="text-sm text-gray-500 text-left space-y-2 mb-4">
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-gray-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Check your spam or junk folder</span>
                </li>
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-gray-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Make sure the email address is correct</span>
                </li>
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-gray-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Wait a few minutes for the email to arrive</span>
                </li>
            </ul>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300">
                    Resend Verification Email
                </button>
            </form>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-400 hover:text-indigo-400 transition-colors">
                Sign out and use a different account
            </button>
        </form>
    </div>
</x-guest-layout>
