<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$oldFile = "trains.json";
$newFile = "new_trains.json";
$stationFile = "stations.json";

// ------------ LOAD STATIONS ------------
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

function stationName($c, $stations) {
    $c = strtoupper($c);
    return $stations[$c] ?? $c;
}

function cleanTime($t) {
    if (!$t || strtolower($t) == "n/a") return "N/A";
    if (preg_match("/^[0-9]{2}:[0-9]{2}$/", $t)) return $t . ":00";
    return $t;
}

// ------------ INPUT CHECK ------------
if (!isset($_GET["q"])) {
    echo json_encode([
        "error" => "Use ?q=express or ?q=04601",
        "example" => "search.php?q=raj"
    ], JSON_PRETTY_PRINT);
    exit;
}

$q = strtolower(trim($_GET["q"]));
$results = [];


// ================================
// 🔵 SEARCH OLD trains.json
// ================================
if (file_exists($oldFile)) {
    $old = json_decode(file_get_contents($oldFile), true);

    foreach ($old as $r) {
        $num = strtolower($r["train_no"]);
        $name = strtolower($r["train_name"]);

        if (str_contains($num, $q) || str_contains($name, $q)) {

            // Collect all rows with same train number
            $rows = array_values(array_filter($old, function($X) use ($r){
                return $X["train_no"] == $r["train_no"];
            }));

            $first = $rows[0];
            $last  = $rows[count($rows)-1];

            $results[] = [
                "from_database" => "OLD",
                "train_number"  => $first["train_no"],
                "train_name"    => $first["train_name"],

                "source" => [
                    "code" => $first["source"],
                    "name" => stationName($first["source"], $stations),
                    "arrival_time" => cleanTime($first["arrival"]),
                    "departure_time" => cleanTime($first["departure"])
                ],

                "destination" => [
                    "code" => $last["destination"],
                    "name" => stationName($last["destination"], $stations),
                    "arrival_time" => cleanTime($last["arrival"]),
                    "departure_time" => cleanTime($last["departure"])
                ],

                "route_summary" => [
                    "total_stops" => count($rows),
                    "source_station" => stationName($first["source"], $stations),
                    "destination_station" => stationName($last["destination"], $stations),
                ],

                "raw_rows" => $rows
            ];
        }
    }
}


// ================================
// 🔵 SEARCH NEW GEOJSON
// ================================
if (file_exists($newFile)) {
    $new = json_decode(file_get_contents($newFile), true);

    if (isset($new["features"])) {
        foreach ($new["features"] as $f) {
            if (!isset($f["properties"])) continue;

            $p = $f["properties"];

            $num  = strtolower($p["number"]);
            $name = strtolower($p["name"]);

            if (str_contains($num, $q) || str_contains($name, $q)) {

                $results[] = [
                    "from_database" => "GEOJSON_NEW",

                    "train_number" => $p["number"],
                    "train_name"   => $p["name"],
                    "zone"         => $p["zone"],
                    "type"         => $p["type"],
                    "distance_km"  => $p["distance"],
                    "duration_m"   => $p["duration_m"],
                    "return_train" => $p["return_train"],

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

                    "coordinates" => $f["geometry"]["coordinates"] ?? []
                ];
            }
        }
    }
}


// ================================
// 🔴 No Results
// ================================
if (empty($results)) {
    echo json_encode([
        "status" => "NO_MATCH",
        "query" => $q
    ], JSON_PRETTY_PRINT);
    exit;
}


// ================================
// 🟢 Output All Full Train Details
// ================================
echo json_encode([
    "status" => "OK",
    "query" => $q,
    "results" => $results
], JSON_PRETTY_PRINT);
?>