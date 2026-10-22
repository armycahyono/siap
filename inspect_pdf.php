<?php

use Smalot\PdfParser\Parser;

require __DIR__.'/vendor/autoload.php';
$parser = new Parser;
$pdf = $parser->parseFile(__DIR__.'/storage/app/public/timetables/Jadwal_Kelas_aSc_Timetables_Asli.pdf');
$pages = $pdf->getPages();
echo 'Total pages: '.count($pages)."\n";
echo "--- PAGE 1 ---\n";
echo $pages[0]->getText()."\n";
echo "--- PAGE 2 ---\n";
echo $pages[1]->getText()."\n";
