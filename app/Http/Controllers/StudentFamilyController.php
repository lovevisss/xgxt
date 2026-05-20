<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentFamily;
use App\Support\CurrentUser;

class StudentFamilyController extends Controller
{
    public function index()
    {
        $query = StudentFamily::query()
            ->leftJoin('students', 'students.xgh', '=', 'student_families.stu_no')
            ->select('student_families.*', 'students.xm as student_name');

        $keyword = trim((string) request('q', ''));
        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('student_families.stu_no', 'like', "%{$keyword}%")
                    ->orWhere('students.xm', 'like', "%{$keyword}%")
                    ->orWhere('student_families.phone', 'like', "%{$keyword}%")
                    ->orWhere('student_families.work_unit', 'like', "%{$keyword}%");
            });
        }

        $emergency = trim((string) request('emergency', ''));
        if ($emergency === '1') {
            $query->where('is_emergency_contact', true);
        } elseif ($emergency === '0') {
            $query->where('is_emergency_contact', false);
        }

        $records = $query
            ->orderBy('student_families.stu_no')
            ->orderByDesc('student_families.is_emergency_contact')
            ->orderBy('student_families.id')
            ->paginate(15);

        return response()->json($records);
    }

    public function show($id)
    {
        $record = StudentFamily::query()->findOrFail($id);

        return response()->json($record);
    }

    public function update($id)
    {
        $record = StudentFamily::query()->findOrFail($id);
        $student = Student::query()->where('xgh', $record->stu_no)->first();

        abort_unless(
            CurrentUser::canManageDepartment($student?->dwbm),
            403,
            'Only counselors in the student department or super admins can update family records.'
        );

        $record->fill(request()->only([
            'name',
            'relationship',
            'specific_relationship',
            'work_unit',
            'position',
            'phone',
            'is_emergency_contact',
        ]));

        if (request()->has('is_emergency_contact')) {
            $record->is_emergency_contact = (int) request('is_emergency_contact') === 1;
        }

        $record->is_local_modified = true;
        $record->local_modified_at = now();
        $record->updated_at = now();
        $record->save();

        return response()->json($record);
    }
}
