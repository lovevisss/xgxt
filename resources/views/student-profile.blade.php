@extends('layouts.vue-app')

@section('title', '学生主页')
@section('page', 'studentProfile')
@section('props')
@php
    $pageProps = [
        "student" => $student,
        "families" => $families,
        "awards" => $awards,
        "punishments" => $punishments,
        "loans" => $loans,
        "supportRecipients" => $supportRecipients,
        "medicalInsurances" => $medicalInsurances,
        "currentMedicalInsurance" => $currentMedicalInsurance,
        "safetyInsurances" => $safetyInsurances,
        "currentSafetyInsurance" => $currentSafetyInsurance,
        "physicalTests" => $physicalTests,
        "currentYear" => $currentYear,
        "dormitory" => $dormitory,
        "dormitorySummary" => $dormitorySummary,
        "roommates" => $roommates,
        "selectedSemester" => $selectedSemester,
        "semesterLabel" => $semesterLabel,
        "selectedWeek" => $selectedWeek,
        "weekLabel" => $weekLabel,
        "prevWeekUrl" => $prevWeekUrl,
        "nextWeekUrl" => $nextWeekUrl,
        "weeklySchedule" => $weeklySchedule,
        "gradesBySemester" => $gradesBySemester,
        "earnedCreditsTotal" => $earnedCreditsTotal,
        "averageGpa" => $averageGpa,
        "recentPasses" => $recentPasses,
        "companionInsights" => $companionInsights,
        "canUpdateFamilies" => $canUpdateFamilies,
    ];
@endphp
@json($pageProps)
@endsection
