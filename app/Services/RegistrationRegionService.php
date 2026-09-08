<?php

namespace App\Services;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\Village;
use Illuminate\Validation\ValidationException;

class RegistrationRegionService
{
    /**
     * Decide whether submitted region state represents a real region edit.
     *
     * Legacy registrations may contain only text names. Blank code fields must
     * not erase those names when another field is edited.
     *
     * @param array<string, mixed> $data
     */
    public function shouldNormalize(array $data, ?Registration $registration = null): bool
    {
        foreach (['province_code', 'city_code', 'district_code', 'village_code'] as $field) {
            if (filled($data[$field] ?? null) || filled($registration?->{$field})) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize region codes into canonical names while validating the hierarchy.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        $regionCodeFields = ['province_code', 'city_code', 'district_code', 'village_code'];

        if (! collect($regionCodeFields)->contains(fn (string $field): bool => array_key_exists($field, $data))) {
            return $data;
        }

        $provinceCode = $this->nullableCode($data['province_code'] ?? null);

        if (! $provinceCode) {
            return $this->clearFrom($data, 'province');
        }

        $province = Province::query()->find($provinceCode);

        if (! $province) {
            throw ValidationException::withMessages([
                'province_code' => 'Provinsi yang dipilih tidak tersedia pada master wilayah.',
            ]);
        }

        $data['province_code'] = $province->code;
        $data['province'] = $province->name;

        $cityCode = $this->nullableCode($data['city_code'] ?? null);

        if (! $cityCode) {
            return $this->clearFrom($data, 'city');
        }

        $regency = Regency::query()
            ->whereKey($cityCode)
            ->where('province_code', $province->code)
            ->first();

        if (! $regency) {
            throw ValidationException::withMessages([
                'city_code' => 'Kabupaten/Kota tidak sesuai dengan provinsi yang dipilih.',
            ]);
        }

        $data['city_code'] = $regency->code;
        $data['city'] = $regency->name;

        $districtCode = $this->nullableCode($data['district_code'] ?? null);

        if (! $districtCode) {
            return $this->clearFrom($data, 'district');
        }

        $district = District::query()
            ->whereKey($districtCode)
            ->where('regency_code', $regency->code)
            ->first();

        if (! $district) {
            throw ValidationException::withMessages([
                'district_code' => 'Kecamatan tidak sesuai dengan Kabupaten/Kota yang dipilih.',
            ]);
        }

        $data['district_code'] = $district->code;
        $data['district'] = $district->name;

        $villageCode = $this->nullableCode($data['village_code'] ?? null);

        if (! $villageCode) {
            return $this->clearFrom($data, 'village');
        }

        $village = Village::query()
            ->whereKey($villageCode)
            ->where('district_code', $district->code)
            ->first();

        if (! $village) {
            throw ValidationException::withMessages([
                'village_code' => 'Desa/Kelurahan tidak sesuai dengan Kecamatan yang dipilih.',
            ]);
        }

        $data['village_code'] = $village->code;
        $data['village'] = $village->name;

        return $data;
    }

    private function nullableCode(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function clearFrom(array $data, string $level): array
    {
        $levels = [
            'province' => ['province', 'city', 'district', 'village'],
            'city' => ['city', 'district', 'village'],
            'district' => ['district', 'village'],
            'village' => ['village'],
        ];

        $codeFields = [
            'province' => 'province_code',
            'city' => 'city_code',
            'district' => 'district_code',
            'village' => 'village_code',
        ];

        foreach ($levels[$level] as $regionLevel) {
            $data[$regionLevel] = null;
            $data[$codeFields[$regionLevel]] = null;
        }

        return $data;
    }
}
