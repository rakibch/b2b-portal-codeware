<?php

namespace App\Services\Company;

use App\Enums\AddressType;
use App\Exceptions\BusinessRuleException;
use App\Models\Company;
use App\Models\CompanyAddress;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function create(Company $company, array $data): CompanyAddress
    {
        return DB::transaction(function () use ($company, $data): CompanyAddress {
            $type = AddressType::from($data['type']);
            $isFirst = ! $company->addresses()->where('type', $type->value)->exists();
            $makeDefault = $isFirst || ! empty($data['is_default']);

            if ($makeDefault) {
                $this->clearDefault($company, $type);
            }

            return $company->addresses()->create(['is_default' => $makeDefault] + $data);
        });
    }

    public function update(CompanyAddress $address, array $data): CompanyAddress
    {
        return DB::transaction(function () use ($address, $data): CompanyAddress {
            unset($data['type']); // type is immutable; create a new address instead

            if (! empty($data['is_default'])) {
                $this->clearDefault($address->company, $address->type);
            } elseif (array_key_exists('is_default', $data) && $address->is_default) {
                unset($data['is_default']); // there must always be one default per type
            }

            $address->update($data);

            return $address;
        });
    }

    public function delete(CompanyAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $siblings = $address->company->addresses()->where('type', $address->type->value)->whereKeyNot($address->id);

            if ($address->type === AddressType::Billing && ! $siblings->exists()) {
                throw BusinessRuleException::make('address_last_billing');
            }

            if ($address->type === AddressType::Shipping && ! $siblings->exists()) {
                throw BusinessRuleException::make('address_last_shipping');
            }

            $address->delete();

            if ($address->is_default) {
                $siblings->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    private function clearDefault(Company $company, AddressType $type): void
    {
        $company->addresses()->where('type', $type->value)->where('is_default', true)->update(['is_default' => false]);
    }
}
