<?php

namespace App\Services\Personnel;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

class OrganizationHierarchyService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{directorate: Directorate, department: Department, unit: Unit, section: ?Section, position: Position}
     */
    public function validatedTarget(array $attributes): array
    {
        $directorate = Directorate::query()->where('active', true)->find($attributes['new_directorate_id'] ?? null);
        $department = Department::query()->where('active', true)->find($attributes['new_department_id'] ?? null);
        $unit = Unit::query()->where('active', true)->find($attributes['new_unit_id'] ?? null);
        $sectionId = $attributes['new_section_id'] ?? null;
        $section = $sectionId === null ? null : Section::query()->where('active', true)->find($sectionId);
        $position = Position::query()->where('active', true)->find($attributes['new_position_id'] ?? null);
        $errors = [];

        if ($directorate === null) {
            $errors['new_directorate_id'] = 'Directorate tujuan tidak tersedia atau tidak aktif.';
        }

        if ($department === null) {
            $errors['new_department_id'] = 'Department tujuan tidak tersedia atau tidak aktif.';
        } elseif ($directorate !== null && (int) $department->directorate_id !== (int) $directorate->id) {
            $errors['new_department_id'] = 'Department tujuan tidak berada dalam Directorate yang dipilih.';
        }

        if ($unit === null) {
            $errors['new_unit_id'] = 'Unit tujuan tidak tersedia atau tidak aktif.';
        } elseif ($department !== null && (int) $unit->department_id !== (int) $department->id) {
            $errors['new_unit_id'] = 'Unit tujuan tidak berada dalam Department yang dipilih.';
        }

        if ($sectionId !== null && $section === null) {
            $errors['new_section_id'] = 'Section tujuan tidak tersedia atau tidak aktif.';
        } elseif ($section !== null && $unit !== null && (int) $section->unit_id !== (int) $unit->id) {
            $errors['new_section_id'] = 'Section tujuan tidak berada dalam Unit yang dipilih.';
        }

        if ($position === null) {
            $errors['new_position_id'] = 'Position tujuan tidak tersedia atau tidak aktif.';
        } elseif ($unit !== null && (int) $position->unit_id !== (int) $unit->id) {
            $errors['new_position_id'] = 'Position tujuan tidak berada dalam Unit yang dipilih.';
        } elseif ($position->section_id !== null && (int) $position->section_id !== (int) $section?->id) {
            $errors['new_position_id'] = 'Position tujuan tidak berada dalam Section yang dipilih.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return compact('directorate', 'department', 'unit', 'section', 'position');
    }
}
