<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$oldFile = "trains.json";
$newFile = "new_trains.json";
$stationFile = "stations.json";

// ------------------ LOAD STATIONS ------------------
$stations = [];
if (file_exists($stationFile)) {
    $raw = json_decode(file_get_contents($stationFile), true);
    if (is_array($raw)) {
        foreach ($raw as $s) {
            if (isset($s[0]) && isset($s[1])) {
                $stations[strtoupper(trim($s[0]))] = trim($s[1]);
            }
        }
    }
}

function stationName($code, $stations) {
    $code = strtoupper($code);
    return $stations[$code] ?? $code;
}

function cleanTime($t) {
    if (!$t || strtolower($t) === "n/a") return "N/A";
    if (preg_match("/^[0-9]{2}:[0-9]{2}$/", $t)) return $t . ":00";
    return $t;
}

// ------------------ INPUT ------------------
if (!isset($_GET["train"])) {
    echo json_encode([
        "error" => "Use ?train=04601",
        "example" => "index.php?train=04601"
    ], JSON_PRETTY_PRINT);
    exit;
}

$trainID = trim($_GET["train"]);

// ------------------ TRY OLD DATABASE ------------------
$oldData = file_exists($oldFile) ? json_decode(file_get_contents($oldFile), true) : [];

$oldFound = [];
foreach ($oldData as $r) {
    if (trim($r["train_no"]) === $trainID) {
        $oldFound[] = $r;
    }
}

if (!empty($oldFound)) {
    $first = $oldFound[0];
    $last  = $oldFound[count($oldFound)-1];

    echo json_encode([
        "train_number" => $trainID,
        "train_name" => $first["train_name"],
        "source" => [
            "code" => $first["source"],
            "name" => stationName($first["source"], $stations),
            "arrival_time" => cleanTime($first["arrival"]),
            "departure_time" => cleanTime($first["departure"]),
        ],
        "destination" => [
            "code" => $last["destination"],
            "name" => stationName($last["destination"], $stations),
            "arrival_time" => cleanTime($last["arrival"]),
            "departure_time" => cleanTime($last["departure"]),
        ],
        "source_file" => "OLD"
    ], JSON_PRETTY_PRINT);
    exit;
}

// ------------------ TRY NEW GEOJSON DATABASE ------------------
if (!file_exists($newFile)) {
    echo json_encode(["error" => "new_trains.json missing"], JSON_PRETTY_PRINT);
    exit;
}

$newData = json_decode(file_get_contents($newFile), true);

if (!isset($newData["features"])) {
    echo json_encode(["error" => "Invalid GeoJSON format"], JSON_PRETTY_PRINT);
    exit;
}

foreach ($newData["features"] as $f) {
    if (!isset($f["properties"])) continue;

    $p = $f["properties"];
    if (isset($p["number"]) && trim($p["number"]) === $trainID) {

        // Prepare final JSON
        $result = [
            "train_number" => $p["number"],
            "train_name" => $p["name"] ?? "Unknown",
            "type" => $p["type"] ?? "N/A",
            "zone" => $p["zone"] ?? "N/A",

            "source" => [
                "code" => $p["from_station_code"],
                "name" => stationName($p["from_station_code"], $stations),
                "departure_time" => cleanTime($p["departure"])
            ],

            "destination" => [
                "code" => $p["to_station_code"],
                "name" => stationName($p["to_station_code"], $stations),
                "arrival_time" => cleanTime($p["arrival"])
            ],

            "duration_minutes" => $p["duration_m"] ?? null,
            "distance_km" => $p["distance"] ?? null,
            "return_train" => $p["return_train"] ?? null,

            "coordinates" => $f["geometry"]["coordinates"] ?? [],

            "source_file" => "GEOJSON_NEW"
        ];

        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
}

// Train not found
echo json_encode([
    "error" => "Train not found",
    "train" => $trainID
], JSON_PRETTY_PRINT);
?>