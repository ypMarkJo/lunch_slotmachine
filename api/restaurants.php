<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) {
            continue;
        }

        [$key, $value] = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        if ($key !== "" && getenv($key) === false) {
            putenv($key . "=" . $value);
            $_ENV[$key] = $value;
        }
    }
}

function kakao_get(string $path, array $params, string $apiKey): array
{
    $query = http_build_query($params);
    $url = "https://dapi.kakao.com" . $path . "?" . $query;

    if (!function_exists("curl_init")) {
        throw new RuntimeException("cURL 확장이 비활성화되어 있습니다.");
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ["Authorization: KakaoAK " . $apiKey],
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $status >= 400) {
        throw new RuntimeException("카카오 API 요청 실패: " . ($error !== "" ? $error : ("HTTP " . $status)));
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("카카오 API 응답 파싱 실패");
    }

    return $decoded;
}

function find_center(string $apiKey): array
{
    $payload = kakao_get(
        "/v2/local/search/keyword.json",
        ["query" => "LS용산타워", "size" => 1],
        $apiKey
    );
    $documents = $payload["documents"] ?? [];
    if (!is_array($documents) || count($documents) === 0) {
        throw new RuntimeException("LS용산타워 좌표를 찾지 못했습니다.");
    }

    $center = $documents[0];
    return [(float)($center["x"] ?? 0), (float)($center["y"] ?? 0)];
}

function parse_radius_km(mixed $raw, float $default = 2.0): float
{
    if (is_numeric($raw)) {
        $value = (float)$raw;
    } else {
        $value = $default;
    }

    return max(0.1, min(5.0, round($value, 1)));
}

function parse_coordinate(mixed $raw, float $min, float $max): ?float
{
    if (!is_string($raw) || trim($raw) === "") {
        return null;
    }

    if (!is_numeric($raw)) {
        return null;
    }

    $value = (float)$raw;
    if ($value < $min || $value > $max) {
        return null;
    }

    return $value;
}

function determine_max_pages(float $radiusKm): int
{
    // 카카오 API 최대 지원 페이지 수(45페이지)까지 요청하여 목표 100개 이상 식당을 수집합니다.
    return 45;
}

function offset_coordinate(float $latitude, float $longitude, float $distanceMeter, float $bearingDegree): array
{
    $bearing = deg2rad($bearingDegree);
    $latOffset = ($distanceMeter * cos($bearing)) / 111320.0;
    $lngBase = 111320.0 * max(0.1, cos(deg2rad($latitude)));
    $lngOffset = ($distanceMeter * sin($bearing)) / $lngBase;
    return [$latitude + $latOffset, $longitude + $lngOffset];
}

function build_search_points(float $centerX, float $centerY, int $radius): array
{
    $points = [[$centerX, $centerY]];
    if ($radius >= 200) {
        $ringDistance = $radius * 0.55;
        foreach ([0.0, 45.0, 90.0, 135.0, 180.0, 225.0, 270.0, 315.0] as $bearing) {
            [$lat, $lng] = offset_coordinate($centerY, $centerX, $ringDistance, $bearing);
            $points[] = [$lng, $lat];
        }
    }
    return $points;
}

function distance_meters(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $earthRadius = 6371000.0;
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLng = deg2rad($lng2 - $lng1);

    $a = sin($deltaLat / 2) ** 2
        + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return (int)round($earthRadius * $c);
}

function fetch_nearby_restaurants(
    string $apiKey,
    int $radius = 2000,
    int $maxPages = 45,
    ?float $longitude = null,
    ?float $latitude = null,
    int $targetCount = 200
): array {
    if ($longitude !== null && $latitude !== null) {
        $centerX = $longitude;
        $centerY = $latitude;
    } else {
        [$centerX, $centerY] = find_center($apiKey);
    }

    $points = build_search_points($centerX, $centerY, $radius);
    $items = [];
    $seen = [];

    foreach ($points as $point) {
        [$pointX, $pointY] = $point;
        for ($page = 1; $page <= 45; $page++) {
            $payload = kakao_get(
                "/v2/local/search/category.json",
                [
                    "category_group_code" => "FD6",
                    "x" => $pointX,
                    "y" => $pointY,
                    "radius" => $radius,
                    "sort" => "accuracy",
                    "size" => 15,
                    "page" => $page,
                ],
                $apiKey
            );

            $documents = $payload["documents"] ?? [];
            if (!is_array($documents) || count($documents) === 0) {
                break;
            }

            foreach ($documents as $place) {
                $placeId = (string)($place["id"] ?? "");
                if ($placeId === "" || isset($seen[$placeId])) {
                    continue;
                }

                $placeXRaw = (string)($place["x"] ?? "");
                $placeYRaw = (string)($place["y"] ?? "");
                if (!is_numeric($placeXRaw) || !is_numeric($placeYRaw)) {
                    continue;
                }
                $placeX = (float)$placeXRaw;
                $placeY = (float)$placeYRaw;
                $distanceFromCenter = distance_meters($centerY, $centerX, $placeY, $placeX);
                if ($distanceFromCenter > $radius) {
                    continue;
                }

                $seen[$placeId] = true;

                $categoryName = (string)($place["category_name"] ?? "");
                $leaf = "음식점";
                if ($categoryName !== "") {
                    $parts = explode(">", $categoryName);
                    $leaf = trim((string)$parts[count($parts) - 1]);
                }

                $items[] = [
                    "id" => $placeId,
                    "name" => (string)($place["place_name"] ?? "이름없음"),
                    "category" => $leaf,
                    "area" => (string)($place["address_name"] ?? ($place["road_address_name"] ?? "주소정보없음")),
                    "distance_m" => $distanceFromCenter,
                    "phone" => (string)($place["phone"] ?? ""),
                    "place_url" => (string)($place["place_url"] ?? ""),
                    "rating" => null,
                    "price" => "정보없음",
                ];
            }

            $meta = $payload["meta"] ?? [];
            if (($meta["is_end"] ?? false) === true) {
                break;
            }
            if (count($items) >= $targetCount) {
                break;
            }
        }
        if (count($items) >= $targetCount) {
            break;
        }
    }

    return $items;
}

load_env(__DIR__ . "/../.env");
$sampleRestaurants = require __DIR__ . "/../data/sample_restaurants.php";
$apiKey = getenv("KAKAO_REST_API_KEY");
$radiusKm = parse_radius_km($_GET["radius_km"] ?? null, 0.5);
$radiusMeter = (int)round($radiusKm * 1000);
$maxPages = determine_max_pages($radiusKm);
$latitude = parse_coordinate($_GET["lat"] ?? null, -90.0, 90.0);
$longitude = parse_coordinate($_GET["lng"] ?? null, -180.0, 180.0);
$usingCurrentLocation = $latitude !== null && $longitude !== null;
$locationLabel = $usingCurrentLocation ? "현재 위치" : "LS용산타워";

$response = [
    "source" => "샘플 데이터",
    "location" => $locationLabel,
    "notice" => "KAKAO_REST_API_KEY 미설정으로 샘플 데이터를 표시 중입니다. (반경 {$radiusKm}km)",
    "restaurants" => $sampleRestaurants,
];

if (is_string($apiKey) && trim($apiKey) !== "") {
    try {
        $restaurants = fetch_nearby_restaurants($apiKey, $radiusMeter, $maxPages, $longitude, $latitude);
        if (count($restaurants) > 0) {
            $response = [
                "source" => "카카오 로컬 API",
                "location" => $locationLabel,
                "notice" => "{$locationLabel} 반경 {$radiusKm}km 식당 데이터를 표시 중입니다. (평점은 공식 Local API 미제공)",
                "restaurants" => $restaurants,
            ];
        } else {
            $response["notice"] = "{$locationLabel} 반경 {$radiusKm}km 조회 결과가 비어 샘플 데이터로 대체했습니다.";
        }
    } catch (Throwable $e) {
        $response["notice"] = "카카오 조회 실패로 샘플 데이터로 대체했습니다. (" . $e->getMessage() . ")";
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
