<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\AcademicYear;
use App\Models\PiketJournal;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

// Fetch academic years with their date ranges
$academicYears = AcademicYear::all()->map(function ($ay) {
    return [
        'name' => $ay->name,
        'start' => $ay->start_date ? Carbon::parse($ay->start_date) : null,
        'end' => $ay->end_date ? Carbon::parse($ay->end_date) : null,
    ];
})->keyBy('name')->toArray();

// Detect overlapping ranges (ambiguous dates)
$overlaps = [];
$names = array_keys($academicYears);
for ($i = 0; $i < count($names); $i++) {
    $a = $academicYears[$names[$i]];
    if (! $a['start'] || ! $a['end']) {
        continue;
    }
    for ($j = $i + 1; $j < count($names); $j++) {
        $b = $academicYears[$names[$j]];
        if (! $b['start'] || ! $b['end']) {
            continue;
        }
        // Check if ranges intersect (inclusive)
        if ($a['start'] <= $b['end'] && $b['start'] <= $a['end']) {
            $overlaps[] = [$a['name'], $b['name']];
        }
    }
}

$journals = PiketJournal::with(['coordinator', 'attendances', 'studentPermits', 'incidents', 'guests', 'invalClasses'])->get();

$results = [];
$summary = [
    'total' => $journals->count(),
    'categories' => [],
    'ids' => [],
];

foreach ($journals as $j) {
    $date = Carbon::parse($j->date);
    $yearName = $j->academic_year;
    $category = 'UNDETERMINED';
    $matches = [];

    if (! isset($academicYears[$yearName])) {
        $category = 'UNKNOWN_YEAR';
    } else {
        $range = $academicYears[$yearName];
        if (! $range['start'] || ! $range['end']) {
            $category = 'INVALID_RANGE';
        } else {
            $inRange = $date->betweenIncluded($range['start'], $range['end']);
            // Check ambiguous: date falls into another year range as well
            $ambiguous = false;
            foreach ($academicYears as $otherName => $otherRange) {
                if ($otherName === $yearName) {
                    continue;
                }
                if (! $otherRange['start'] || ! $otherRange['end']) {
                    continue;
                }
                if ($date->betweenIncluded($otherRange['start'], $otherRange['end'])) {
                    $ambiguous = true;
                    break;
                }
            }
            if ($ambiguous) {
                $category = 'AMBIGUOUS_DATE';
            } elseif ($inRange) {
                $category = 'MATCH';
            } else {
                $category = 'DATE_MISMATCH';
            }
        }
    }

    $results[] = [
        'id' => $j->id,
        'date' => $j->date->toDateString(),
        'academic_year' => $yearName,
        'category' => $category,
        'has_relations' => $j->attendances->isNotEmpty() || $j->studentPermits->isNotEmpty() || $j->incidents->isNotEmpty() || $j->guests->isNotEmpty() || $j->invalClasses->isNotEmpty(),
        'coordinator_exists' => $j->coordinator ? true : false,
        'status' => $j->status,
    ];

    $summary['categories'][$category] = ($summary['categories'][$category] ?? 0) + 1;
    if ($category !== 'MATCH') {
        $summary['ids'][$category][] = $j->id;
    }
}

$report = [
    'summary' => $summary,
    'overlaps' => $overlaps,
    'journals' => $results,
];

echo json_encode($report, JSON_PRETTY_PRINT);
