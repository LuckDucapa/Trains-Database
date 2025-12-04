<?php
// manage.php
// Usage: visit http://127.0.0.1/manage.php to (re)create trains.json from CSV
set_time_limit(0);
ini_set('memory_limit', '512M');

$csvFileName = "Train_details_22122017.csv";
$jsonFileName = "trains.json";

if (!file_exists($csvFileName)) {
    http_response_code(500);
    echo "CSV not found. Upload {$csvFileName} to the same folder and reload this page.";
    exit;
}

// open csv and parse streaming
$fh = fopen($csvFileName, "r");
if (!$fh) {
    http_response_code(500);
    echo "Unable to open CSV file.";
    exit;
}

// Read header row and normalize keys
$header = fgetcsv($fh);
if ($header === FALSE) {
    fclose($fh);
    http_response_code(500);
    echo "CSV appears empty or corrupted.";
    exit;
}
$normHeader = [];
foreach ($header as $h) {
    $k = trim(strtolower($h));
    // normalize common variants to standardized key names
    $k = str_replace([' ', '.', '/', '-'], '_', $k);
    $normHeader[] = $k;
}

// We will map common column names to friendly names
$mapCandidates = [
    'train_no' => ['train_no','train_no.','train no','train no.','train_number','train number','trainno','number'],
    'train_name' => ['train_name','train name','name','trainname'],
    'source' => ['source','source_station','from_station','from','origin'],
    'destination' => ['destination','destination_station','to_station','to','dest'],
    'arrival' => ['arrival','arrival_time','arr_time','arr'],
    'departure' => ['departure','departure_time','dep_time','dep'],
    'distance' => ['distance','dist','kms'],
    'day' => ['day','days','running_days','day_of_run'],
    'route' => ['route','full_route','stops','stations']
];

function find_key_index($normHeader, $candidates) {
    foreach ($candidates as $cand) {
        $i = array_search($cand, $normHeader);
        if ($i !== false) return $i;
    }
    // try partial matches
    foreach ($normHeader as $idx => $h) {
        foreach ($candidates as $cand) {
            if (strpos($h, $cand) !== false) return $idx;
        }
    }
    return null;
}

// build index map for each desired field
$indexMap = [];
foreach ($mapCandidates as $std => $cands) {
    $index = find_key_index($normHeader, $cands);
    $indexMap[$std] = $index; // may be null
}

// Read rows and assemble standardized array
$result = [];
while (($row = fgetcsv($fh)) !== FALSE) {
    // skip empty rows
    if (count(array_filter($row)) === 0) continue;

    $item = [];
    // safe-get by index
    foreach ($indexMap as $std => $idx) {
        $value = ($idx !== null && isset($row[$idx])) ? trim($row[$idx]) : '';
        $item[$std] = $value;
    }
    // fallback: if train_no is empty try first column
    if (empty($item['train_no'])) {
        $item['train_no'] = isset($row[0]) ? trim($row[0]) : '';
    }
    // fallback: train_name from second column
    if (empty($item['train_name'])) {
        $item['train_name'] = isset($row[1]) ? trim($row[1]) : '';
    }

    // normalize train_no: remove non-digit/letter whitespace
    $item['train_no'] = preg_replace('/\s+/', '', $item['train_no']);

    $result[] = $item;
}
fclose($fh);

// Write to JSON (pretty)
file_put_contents($jsonFileName, json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

echo "<pre>";
echo "Imported " . count($result) . " records to {$jsonFileName}\n\n";
echo "Now test on your local site:\n";
echo "http://127.0.0.1/index.php?train=12050\n";
echo "\nIf your server uses a port (for example php -S 127.0.0.1:8000) use:\n";
echo "http://127.0.0.1:8000/index.php?train=12050\n";
echo "</pre>";