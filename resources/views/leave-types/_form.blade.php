@php($input = 'form-control')
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div class="sm:col-span-2">
        <label for="name" class="form-label">Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $leaveType->name) }}" required placeholder="e.g. Annual leave" class="{{ $input }}">
        @error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="code" class="form-label">Code</label>
        <input type="text" name="code" id="code" value="{{ old('code', $leaveType->code) }}" placeholder="e.g. AL" class="{{ $input }}">
        @error('code')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="days_per_year" class="form-label">Days per year <span class="text-red-500">*</span></label>
        <input type="number" min="0" max="366" name="days_per_year" id="days_per_year" value="{{ old('days_per_year', $leaveType->days_per_year ?? 0) }}" required class="{{ $input }}">
        @error('days_per_year')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_paid" value="0">
        <input type="checkbox" name="is_paid" id="is_paid" value="1" @checked(old('is_paid', $leaveType->is_paid)) class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600">
        <label for="is_paid" class="text-sm text-gray-700 dark:text-gray-300">Paid leave</label>
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $leaveType->is_active)) class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600">
        <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active (can be chosen on leave requests)</label>
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_carry_forward" value="0">
        <input type="checkbox" name="is_carry_forward" id="is_carry_forward" value="1" @checked(old('is_carry_forward', $leaveType->is_carry_forward)) class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600">
        <label for="is_carry_forward" class="text-sm text-gray-700 dark:text-gray-300">Unused days carry forward</label>
    </div>
    <div>
        <label for="max_carry_forward_days" class="form-label">Most days carried forward</label>
        <input type="number" min="0" max="366" name="max_carry_forward_days" id="max_carry_forward_days" value="{{ old('max_carry_forward_days', $leaveType->max_carry_forward_days ?? 0) }}" class="{{ $input }}">
        @error('max_carry_forward_days')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="description" class="form-label">Description</label>
        <textarea name="description" id="description" rows="3" class="{{ $input }}">{{ old('description', $leaveType->description) }}</textarea>
        @error('description')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
</div>
