<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftInput
{
    public static function validate(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'time_in' => ['required', 'date_format:H:i,H:i:s'],
            'time_out' => ['required', 'date_format:H:i,H:i:s'],
        ]);
        foreach (['time_in', 'time_out'] as $field) {
            if (strlen($data[$field]) === 5) {
                $data[$field] .= ':00';
            }
        }
        if ($data['time_in'] === $data['time_out']) {
            throw ValidationException::withMessages(['time_out' => 'Jam pulang harus berbeda dari jam masuk.']);
        }

        return $data;
    }
}
