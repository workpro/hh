<?php

namespace App\Rules;

use App\Models\Application;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\DataAwareRule;

class Free implements DataAwareRule, ValidationRule
{

//    это позволяет закинуть в правило все данные массива реквест
    protected $data = [];
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * тут логика проверки
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if ($this->data['type_premises_id'] == 1){
//            если тип Беседки то даты начала и конца НЕ могут пересекаться
            $busy = Application::make()
                ->where('is_active', '1')
                ->where('type_premises_id', $this->data['type_premises_id'])
                ->where('status_id', '!=', 4)
                ->where('check_in', '>=', Carbon::parse($this->data['check-in'])->format('Y-m-d'))
                ->where('check_in', '<=', Carbon::parse($this->data['check-out'])->format('Y-m-d'));
            if (isset($this->data['app_id'])){
                $busy->where('id', '!=', $this->data['app_id']);
            }
            $busy->orWhere('status_id', '!=', 4)
                ->where('is_active', '1')
                ->where('type_premises_id', $this->data['type_premises_id'])
                ->where('check_out', '<=', Carbon::parse($this->data['check-out'])->format('Y-m-d'))
                ->where('check_out', '>=', Carbon::parse($this->data['check-in'])->format('Y-m-d'))
            ;
            if (isset($this->data['app_id'])){
                $busy->where('id', '!=', $this->data['app_id']);
            }
            $busy = $busy->get();
        }
        elseif ($this->data['type_premises_id'] == 2)
        {
//            если тип Номера то даты начала и конца могут пересекаться
            $busy = Application::make()
                ->where('is_active', '1')
                ->where('type_premises_id', $this->data['type_premises_id'])
                ->where('status_id', '!=', 4)
                ->where('check_in', '>', Carbon::parse($this->data['check-in'])->format('Y-m-d'))
                ->where('check_in', '<', Carbon::parse($this->data['check-out'])->format('Y-m-d'));
            if (isset($this->data['app_id'])){
                $busy->where('id', '!=', $this->data['app_id']);
            }

            $busy->orWhere('status_id', '!=', 4)
                ->where('is_active', '1')
                ->where('type_premises_id', $this->data['type_premises_id'])
                ->where('check_out', '<', Carbon::parse($this->data['check-out'])->format('Y-m-d'))
                ->where('check_out', '>', Carbon::parse($this->data['check-in'])->format('Y-m-d'))
            ;
            if (isset($this->data['app_id'])){
                $busy->where('id', '!=', $this->data['app_id']);
            }

            $busy->orWhere('status_id', '!=', 4)
                ->where('is_active', '1')
                ->where('type_premises_id', $this->data['type_premises_id'])
                ->where('check_in', '=', Carbon::parse($this->data['check-in'])->format('Y-m-d'))
                ->where('check_out', '=', Carbon::parse($this->data['check-out'])->format('Y-m-d'))
            ;
            if (isset($this->data['app_id'])){
                $busy->where('id', '!=', $this->data['app_id']);
            }

            $busy = $busy->get();
        }

//      и все номера комнат из формы
        $prem_ids = collect($this->data['rents'])->pluck(['premise']);

        foreach ($busy->pluck(['premises']) as $premise){
            foreach ($premise as $item){
//                если есть пересечение в массивах - то ошибка
                if ($prem_ids->contains($item->premise_id)){
                    $fail('validation.busy_premise')->translate();
                }
            }
        }
    }
}
