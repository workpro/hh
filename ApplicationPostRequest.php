<?php

namespace App\Http\Requests;

use App\Rules\Free;
use Illuminate\Foundation\Http\FormRequest;

class ApplicationPostRequest extends FormRequest
{
    /**
     * обязательно иначе авторизацию не пройдет
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Тут логика для проверки
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'app_id' =>  'nullable',  // тут опрос персонального правила Free
            'phone' =>  ['required', new Free],  // тут опрос персонального правила Free
            'name' =>  ['required'],
            'total' =>  'required',
            'pay' =>  '',
            'type_premises_id' =>  '',
            'adult' =>  'required|integer',
            'child' =>  'required|integer',
            'comment' =>  '',
            'check-in' =>  'required',
            'check-out' =>  'required',
            'status_id' =>  'required',
            'rents' => [],
            'rents.*.category' => 'required|integer',
            'rents.*.premise' => 'required|integer',
        ];
    }
}
