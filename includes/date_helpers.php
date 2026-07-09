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

function normalizeGregorianDateInput($value) {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
    } elseif (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $text, $matches)) {
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];
    } else {
        return '';
    }

    if ($year > 2400) {
        $year -= 543;
    }

    if (!checkdate($month, $day, $year)) {
        return '';
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function requireGregorianDateInput($value, $message) {
    $date = normalizeGregorianDateInput($value);
    if ($date === '') {
        throw new InvalidArgumentException($message);
    }

    return $date;
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
