<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Value must be an active staff user holding Sales Manager or Super Admin role. */
class AccountManagerRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $valid = User::query()
            ->staff()
            ->active()
            ->whereKey((int) $value)
            ->role([Role::SalesManager->value, Role::SuperAdmin->value])
            ->exists();

        if (! $valid) {
            $fail(__('messages.validation.account_manager_invalid'));
        }
    }
}
