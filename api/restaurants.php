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

function fetch_nearby_restaurants(string $apiKey, int $radius = 2000, int $maxPages = 3): array
{
    [$x, $y] = find_center($apiKey);
    $items = [];
    $seen = [];

    for ($page = 1; $page <= $maxPages; $page++) {
        $payload = kakao_get(
            "/v2/local/search/category.json",
            [
                "category_group_code" => "FD6",
                "x" => $x,
                "y" => $y,
                "radius" => $radius,
                "sort" => "distance",
                "size" => 15,
                "page" => $page,
            ],
            $apiKey
        );

        $documents = $payload["documents"] ?? [];
        if (!is_array($documents)) {
            continue;
        }

        foreach ($documents as $place) {
            $placeId = (string)($place["id"] ?? "");
            if ($placeId === "" || isset($seen[$placeId])) {
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
                "distance_m" => (int)($place["distance"] ?? 0),
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
    }

    return $items;
}

load_env(__DIR__ . "/../.env");
$sampleRestaurants = require __DIR__ . "/../data/sample_restaurants.php";
$apiKey = getenv("KAKAO_REST_API_KEY");

$response = [
    "source" => "샘플 데이터",
    "notice" => "KAKAO_REST_API_KEY 미설정으로 샘플 데이터를 표시 중입니다.",
    "restaurants" => $sampleRestaurants,
];

if (is_string($apiKey) && trim($apiKey) !== "") {
    try {
        $restaurants = fetch_nearby_restaurants($apiKey, 2000, 3);
        if (count($restaurants) > 0) {
            $response = [
                "source" => "카카오 로컬 API",
                "notice" => "LS용산타워 반경 2km 식당 데이터를 표시 중입니다. (평점은 공식 Local API 미제공)",
                "restaurants" => $restaurants,
            ];
        } else {
            $response["notice"] = "카카오 조회 결과가 비어 샘플 데이터로 대체했습니다.";
        }
    } catch (Throwable $e) {
        $response["notice"] = "카카오 조회 실패로 샘플 데이터로 대체했습니다. (" . $e->getMessage() . ")";
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
