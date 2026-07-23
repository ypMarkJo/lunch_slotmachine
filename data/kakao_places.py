import json
from typing import Any, Optional
from urllib.parse import urlencode
from urllib.request import Request, urlopen


BASE_URL = "https://dapi.kakao.com"


def _kakao_get(path: str, params: dict[str, Any], api_key: str) -> dict[str, Any]:
    query = urlencode(params)
    url = f"{BASE_URL}{path}?{query}"
    request = Request(url, headers={"Authorization": f"KakaoAK {api_key}"})

    try:
        with urlopen(request, timeout=10) as response:
            return json.loads(response.read().decode("utf-8"))
    except Exception as error:
        raise RuntimeError(f"요청 실패: {error}") from error


def _find_center(api_key: str, keyword: str = "LS용산타워") -> tuple[float, float]:
    payload = _kakao_get(
        "/v2/local/search/keyword.json",
        {"query": keyword, "size": 1},
        api_key,
    )

    documents = payload.get("documents", [])
    if not documents:
        raise RuntimeError("LS용산타워 좌표를 찾지 못했습니다.")

    center = documents[0]
    return float(center["x"]), float(center["y"])


import math


def _offset_coordinate(latitude: float, longitude: float, distance_m: float, bearing_deg: float) -> tuple[float, float]:
    bearing = math.radians(bearing_deg)
    lat_offset = (distance_m * math.cos(bearing)) / 111320.0
    lng_base = 111320.0 * max(0.1, math.cos(math.radians(latitude)))
    lng_offset = (distance_m * math.sin(bearing)) / lng_base
    return latitude + lat_offset, longitude + lng_offset


def _distance_meters(lat1: float, lng1: float, lat2: float, lng2: float) -> int:
    earth_radius = 6371000.0
    lat1_rad, lat2_rad = math.radians(lat1), math.radians(lat2)
    delta_lat = math.radians(lat2 - lat1)
    delta_lng = math.radians(lng2 - lng1)
    a = math.sin(delta_lat / 2) ** 2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(delta_lng / 2) ** 2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return int(round(earth_radius * c))


def _build_search_points(center_x: float, center_y: float, radius: int) -> list[tuple[float, float]]:
    points = [(center_x, center_y)]
    if radius >= 200:
        ring_distance = radius * 0.55
        for bearing in range(0, 360, 45):
            lat, lng = _offset_coordinate(center_y, center_x, ring_distance, float(bearing))
            points.append((lng, lat))
    return points


def fetch_nearby_restaurants(
    api_key: str,
    radius: int = 2000,
    max_pages: int = 45,
    latitude: Optional[float] = None,
    longitude: Optional[float] = None,
    target_count: int = 200,
) -> list[dict[str, Any]]:
    if latitude is not None and longitude is not None:
        center_x = longitude
        center_y = latitude
    else:
        center_x, center_y = _find_center(api_key=api_key)

    points = _build_search_points(center_x, center_y, radius)
    items: list[dict[str, Any]] = []
    seen: set[str] = set()

    for point_x, point_y in points:
        for page in range(1, 45):
            payload = _kakao_get(
                "/v2/local/search/category.json",
                {
                    "category_group_code": "FD6",
                    "x": point_x,
                    "y": point_y,
                    "radius": radius,
                    "sort": "accuracy",
                    "size": 15,
                    "page": page,
                },
                api_key,
            )

            docs = payload.get("documents", [])
            if not docs:
                break

            for place in docs:
                place_id = place.get("id")
                if not place_id or place_id in seen:
                    continue

                place_x = float(place.get("x", "0"))
                place_y = float(place.get("y", "0"))
                dist_from_center = _distance_meters(center_y, center_x, place_y, place_x)
                if dist_from_center > radius:
                    continue

                seen.add(place_id)
                category_name = place.get("category_name", "")
                category_leaf = category_name.split(">")[-1].strip() if category_name else "음식점"

                items.append(
                    {
                        "id": place_id,
                        "name": place.get("place_name", "이름없음"),
                        "category": category_leaf,
                        "area": place.get("address_name") or place.get("road_address_name") or "주소정보없음",
                        "distance_m": dist_from_center,
                        "phone": place.get("phone", ""),
                        "place_url": place.get("place_url", ""),
                        "rating": None,
                        "price": "정보없음",
                    }
                )

            meta = payload.get("meta", {})
            if meta.get("is_end"):
                break
            if len(items) >= target_count:
                break

        if len(items) >= target_count:
            break

    return items
