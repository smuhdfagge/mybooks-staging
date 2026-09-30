<!-- Admin Sidebar -->
<aside class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-gray-900 sidebar-transition"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       x-cloak>
    
    <!-- Logo -->
    <div class="flex h-16 items-center justify-between px-4 bg-gradient-to-r from-indigo-600 to-purple-600 flex-shrink-0">
        <a href="{{ route('admin.tenants.index') }}" class="flex items-center space-x-2">
            <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <div>
                <span class="text-lg font-bold text-white">MyBooks</span>
                
            </div>
        </a>
        <button @click="sidebarOpen = false" class="lg:hidden p-1 rounded-md text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-white">
            <span class="sr-only">Close sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 sidebar-scroll">
        <div class="pt-2 pb-2">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Tenant Management</p>
        </div>

        <!-- Dashboard -->
        <a href="{{ route('admin.tenants.index') }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.index') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
        </a>

        <!-- All Tenants -->
        <a href="{{ route('admin.tenants.list') }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.list') && !request('status') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            All Tenants
        </a>

        <div class="pt-4 pb-2">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Subscriptions</p>
        </div>

        <!-- Active Subscriptions -->
        <a href="{{ route('admin.tenants.list', ['status' => 'active']) }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.list') && request('status') === 'active' ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0 {{ request()->routeIs('admin.tenants.list') && request('status') === 'active' ? 'text-white' : 'text-green-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Active
        </a>

        <!-- Trial Subscriptions -->
        <a href="{{ route('admin.tenants.list', ['status' => 'trial']) }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.list') && request('status') === 'trial' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0 {{ request()->routeIs('admin.tenants.list') && request('status') === 'trial' ? 'text-white' : 'text-blue-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            On Trial
        </a>

        <!-- Cancelled Subscriptions -->
        <a href="{{ route('admin.tenants.list', ['status' => 'cancelled']) }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.list') && request('status') === 'cancelled' ? 'bg-red-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0 {{ request()->routeIs('admin.tenants.list') && request('status') === 'cancelled' ? 'text-white' : 'text-red-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Cancelled
        </a>

        <!-- Expired Subscriptions -->
        <a href="{{ route('admin.tenants.list', ['status' => 'expired']) }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.list') && request('status') === 'expired' ? 'bg-yellow-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0 {{ request()->routeIs('admin.tenants.list') && request('status') === 'expired' ? 'text-white' : 'text-yellow-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Expired
        </a>

        <!-- No Subscription -->
        <a href="{{ route('admin.tenants.list', ['status' => 'no_subscription']) }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.list') && request('status') === 'no_subscription' ? 'bg-gray-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            No Subscription
        </a>

        <div class="pt-4 pb-2">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Plans</p>
        </div>

        @php
            $plans = \App\Models\Plan::active()->ordered()->get();
        @endphp

        @foreach($plans as $plan)
            <a href="{{ route('admin.tenants.list', ['plan' => $plan->id]) }}" 
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request('plan') == $plan->id ? 'bg-purple-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    {{ $plan->name }}
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full {{ request('plan') == $plan->id ? 'bg-purple-500' : 'bg-gray-700' }}">
                    {{ $plan->subscriptions()->whereIn('status', ['active', 'trialing'])->count() }}
                </span>
            </a>
        @endforeach

        <div class="pt-4 pb-2">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Administration</p>
        </div>

        <!-- Data protection requests (O7) -->
        <a href="{{ route('admin.data-requests.index') }}"
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.data-requests.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Data Requests
        </a>

        <!-- Admin Users -->
        <a href="{{ route('admin.users.index') }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            Admin Users
        </a>
    </nav>

    <!-- Admin info at bottom -->
    <div class="border-t border-gray-700 p-4 flex-shrink-0">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="h-9 w-9 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center">
                    <span class="text-sm font-medium text-white">{{ strtoupper(substr(auth('admin')->user()->name, 0, 1)) }}</span>
                </div>
            </div>
            <div class="ml-3 min-w-0 flex-1">
                <p class="text-sm font-medium text-white truncate">
                    {{ auth('admin')->user()->name }}
                </p>
                <p class="text-xs text-indigo-300 truncate">
                    Administrator
                </p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="flex-shrink-0">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition-colors" title="Sign out">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>
