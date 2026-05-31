<?php

function formatThaiYear($year, $fallback = '-') {
    if ($year === null || $year === '') {
        return $fallback;
    }

    $year = (int)$year;
    if ($year <= 0) {
        return $fallback;
    }

    return (string)($year + 543);
}

function formatThaiDate($value, $fallback = '-') {
    if ($value === null || trim((string)$value) === '') {
        return $fallback;
    }

    $timestamp = strtotime((string)$value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('d/m/', $timestamp) . formatThaiYear(date('Y', $timestamp), $fallback);
}

function formatThaiDateTime($value, $fallback = '-') {
    if ($value === null || trim((string)$value) === '') {
        return $fallback;
    }

    $timestamp = strtotime((string)$value);
    if ($timestamp === false) {
        return $fallback;
    }

    return formatThaiDate(date('Y-m-d', $timestamp), $fallback) . ' ' . date('H:i', $timestamp);
}
