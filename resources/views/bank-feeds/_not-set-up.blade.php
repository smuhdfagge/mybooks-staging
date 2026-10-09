@unless($live)
    <div class="rounded-lg border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/30 dark:border-yellow-700 p-4 text-sm text-yellow-800 dark:text-yellow-200" data-testid="not-set-up">
        <p class="font-medium">Not set up yet — the MyBooks team needs to add Mono keys.</p>
        <p class="mt-1">Until then no bank can be linked and nothing is read from any bank.</p>
    </div>
@endunless
