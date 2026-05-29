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
        "currentYear" => $currentYear,
        "recentPasses" => $recentPasses,
        "companionInsights" => $companionInsights,
        "canUpdateFamilies" => $canUpdateFamilies,
    ];
@endphp
@json($pageProps)
@endsection
