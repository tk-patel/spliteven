<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'expense_date' => ['required', 'date'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'paid_by' => ['required', 'exists:users,id'],
            'split_type' => ['required', Rule::in(['equal', 'shares', 'percentage', 'exact'])],
            'participants' => ['required', 'array', 'min:2'],
            'participants.*.user_id' => ['required', 'exists:users,id'],
            'participants.*.share_value' => ['nullable', 'numeric', 'min:0'],
            'participants.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
