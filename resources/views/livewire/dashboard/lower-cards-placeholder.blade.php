<div class="grid grid-cols-1 gap-4 lg:grid-cols-3" aria-busy="true" aria-label="Loading more cards">
    @foreach (range(1, 3) as $i)
        <div class="h-56 animate-pulse rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <div class="h-4 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="mt-3 h-7 w-1/2 rounded bg-gray-100 dark:bg-gray-700/60"></div>
            <div class="mt-6 space-y-3">
                <div class="h-3 rounded bg-gray-100 dark:bg-gray-700/60"></div>
                <div class="h-3 w-5/6 rounded bg-gray-100 dark:bg-gray-700/60"></div>
                <div class="h-3 w-4/6 rounded bg-gray-100 dark:bg-gray-700/60"></div>
            </div>
        </div>
    @endforeach
</div>
