{{--
    List of a form's errors at the top of the page (U6), shown by the app
    layout after a failed submit. Each message links to its field, and the
    box takes focus so screen readers announce it.
--}}
@if(isset($errors) && $errors->any())
    <div {{ $attributes->merge(['class' => 'mb-4 rounded-lg bg-red-50 p-4 dark:bg-red-900/50 focus:outline-none']) }}
         role="alert" aria-labelledby="error-summary-title" tabindex="-1" x-data x-init="$el.focus()" data-error-summary>
        <h2 id="error-summary-title" class="text-sm font-medium text-red-800 dark:text-red-200">
            {{ $errors->count() === 1 ? 'Please fix this problem:' : 'Please fix these '.$errors->count().' problems:' }}
        </h2>
        <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
            @foreach($errors->getMessages() as $field => $messages)
                @foreach($messages as $message)
                    <li>
                        @if(! str_contains($field, '.'))
                            <a href="#{{ preg_replace('/[^A-Za-z0-9_-]+/', '-', $field) }}" class="underline hover:no-underline">{{ $message }}</a>
                        @else
                            {{ $message }}
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
