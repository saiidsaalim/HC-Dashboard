<?php

namespace App\Http\Requests;

use App\Services\DashboardLayoutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDashboardLayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->hasPermission('dashboard.view')
            && $this->user()->hasPermission('workspace.hc-od-career-management.view');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $slotKeys = implode(',', array_keys(DashboardLayoutService::SlotTypes));
        $rules = [
            'workspace' => ['required', 'string', Rule::in([DashboardLayoutService::Workspace])],
            'slots' => ['required', 'array:'.$slotKeys, 'required_array_keys:'.$slotKeys, 'size:'.count(DashboardLayoutService::SlotTypes)],
        ];
        $widgets = app(DashboardLayoutService::class)->widgetsFor($this->user());

        foreach (DashboardLayoutService::SlotTypes as $slot => $type) {
            $ids = array_column(array_filter($widgets, fn (array $widget): bool => $widget['type'] === $type), 'id');
            $rules['slots.'.$slot] = ['present', 'nullable', 'string', Rule::in($ids)];
        }

        return $rules;
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $used = [];
            foreach ($this->input('slots') as $slot => $id) {
                if ($id === null) {
                    continue;
                }

                if (in_array($id, $used, true)) {
                    $validator->errors()->add('slots.'.$slot, 'Widget yang sama hanya dapat dipasang satu kali.');
                }
                $used[] = $id;
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'workspace.in' => 'Workspace dashboard tidak valid.',
            'slots.array' => 'Gunakan enam slot dashboard yang tersedia.',
            'slots.required_array_keys' => 'Seluruh enam slot dashboard harus disertakan. Muat ulang dashboard lalu coba lagi.',
            'slots.size' => 'Dashboard menyediakan dua slot ringkas dan empat slot utama.',
            'slots.*.present' => 'Seluruh enam slot dashboard harus disertakan. Muat ulang dashboard lalu coba lagi.',
            'slots.*.in' => 'Widget tidak tersedia, tidak sesuai ukuran slot, atau tidak dapat Anda akses.',
            'slots.*.string' => 'Pilih widget yang tersedia atau kosongkan slot.',
        ];
    }
}
