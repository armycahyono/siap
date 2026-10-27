<?php

use App\Models\AcademicYear;
use App\Models\PiketJournal;
use App\Services\AcademicYearContext;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$operational = app(AcademicYearContext::class)->getOperationalYear()->name;
$years = AcademicYear::all()->mapWithKeys(function ($y) {
    return [$y->name => ['start' => $y->start_date->toDateString(), 'end' => $y->end_date->toDateString()]];
})->toArray();

$journals = PiketJournal::with(['attendances', 'studentPermits', 'incidents', 'guests', 'invalClasses'])->get()->map(function ($j) use ($years, $operational) {
    $year = $j->academic_year;
    $date = $j->date->toDateString();
    $known = array_key_exists($year, $years);
    $inRange = $known && $date >= $years[$year]['start'] && $date <= $years[$year]['end'];
    if ($year === $operational) {
        $category = 'consistent';
    } elseif ($known && $inRange) {
        $category = 'consistent';
    } elseif ($known && ! $inRange) {
        $category = 'out_of_range';
    } else {
        $category = 'indeterminate';
    }
    $hasRelations = $j->attendances->isNotEmpty() || $j->studentPermits->isNotEmpty() || $j->incidents->isNotEmpty() || $j->guests->isNotEmpty() || $j->invalClasses->isNotEmpty();

    return ['id' => $j->id, 'date' => $date, 'academic_year' => $year, 'category' => $category, 'has_relations' => $hasRelations];
});

$summary = [
    'operational_year' => $operational,
    'categories' => ['consistent' => 0, 'out_of_range' => 0, 'indeterminate' => 0],
    'ids' => ['out_of_range' => [], 'indeterminate' => []],
    'journals_without_relations' => [],
];
foreach ($journals as $j) {
    $summary['categories'][$j['category']] = ($summary['categories'][$j['category']] ?? 0) + 1;
    if (in_array($j['category'], ['out_of_range', 'indeterminate'])) {
        $summary['ids'][$j['category']][] = $j['id'];
    }
    if (! $j['has_relations']) {
        $summary['journals_without_relations'][] = $j['id'];
    }
}

echo json_encode(['summary' => $summary, 'journals' => $journals], JSON_PRETTY_PRINT);
