<?php

declare(strict_types=1);

include './vendor/autoload.php';

use Spatie\Fork\Fork;

define(
    'NB_THREAD',
    (function (): int {
        if (PHP_OS_FAMILY == 'Windows') {
            $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
        } else {
            $cores = shell_exec('nproc');
        }
    
        return (int) $cores;
    })()
);

define('FILE', "./measurements.txt");
// petit hack
define('NB_LINES', 1000000000);


// des index en int sans trou pour bénéficier des optis sur les arrays
define('KEY_NB', 0);
define('KEY_SUM', 1);
define('KEY_UPPER', 2);
define('KEY_LOWER', 3);

$dataset = [];

$defaultData = [
    KEY_SUM => 0,
    // des extremes pour limiter les ifs
    KEY_LOWER => 1000,
    KEY_UPPER => -1000,
    KEY_NB => 0
];

$sectionSize = floor(NB_LINES / NB_THREAD);
$forks = [];

for ($threadNumber = 0; $threadNumber < NB_THREAD; $threadNumber++) {
    $forks[] = static function () use ($threadNumber, $defaultData, $sectionSize) : array {
        $file = new SplFileObject(FILE, 'rb');
        $dataset = [];
        // on saute directement au début de la zone à traiter
        $file->seek((int)($sectionSize * $threadNumber));

        $nbRowHandled = 0;
        while ($nbRowHandled < $sectionSize && !$file->eof() && ($line = $file->fgets()) !== '') {
            $pos = strpos($line, ';');
            $city = substr($line, 0, $pos);
            $temp = (float) substr($line, $pos+1, -1);

            if (!isset($dataset[$city])) {
                $dataset[$city] ??= $defaultData;
            }

            // utilisation de reférences pour limiter les accès tableaux
            $cityData = &$dataset[$city];

            $cityData[KEY_NB]++;
            $cityData[KEY_SUM]+= $temp;

            if ($cityData[KEY_UPPER] < $temp) {
                $cityData[KEY_UPPER] = $temp;
            // elseif pour éviter un second if
            } elseif ($cityData[KEY_LOWER] > $temp) {
                $cityData[KEY_LOWER] = $temp;
            }

            $nbRowHandled++;
        }

        return $dataset;
    };
}

$results = Fork::new()
    ->run(
        ...$forks
    );

$dataset = $results[0];
for ($i = 1; $i < NB_THREAD; $i++) {
    foreach ($results[$i] as $city => $values) {
        if (!isset($dataset[$city])) {
            $dataset[$city] = $values;
            continue;
        }
        $cityData = &$dataset[$city];
        $cityData[KEY_NB] += $values[KEY_NB];
        $cityData[KEY_SUM] += $values[KEY_SUM];
        $cityData[KEY_LOWER] = min($values[KEY_LOWER], $cityData[KEY_LOWER]);
        $cityData[KEY_UPPER] = max($values[KEY_UPPER], $cityData[KEY_LOWER]);
    }
    $results[$i] = null; // release memory
}

ksort($dataset);

// write in a buffer to reduce I/O
ob_start();
echo '{';
$first = true;
foreach ($dataset as $key => $values) {
    if (!$first) {
        echo ', ';
    } else {
        $first = false;
    }
    // echo avec des , sans concaténation
    echo $key, '=', round($values[KEY_LOWER], 2), '/', round($values[KEY_SUM]/$values[KEY_NB], 2), '/', round($values[KEY_UPPER], 2);
}
echo '}';
echo ob_get_clean();
echo "\n";