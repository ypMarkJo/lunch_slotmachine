import json
from typing import Any
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


def fetch_nearby_restaurants(api_key: str, radius: int = 2000, max_pages: int = 3) -> list[dict[str, Any]]:
    center_x, center_y = _find_center(api_key=api_key)
    items: list[dict[str, Any]] = []
    seen: set[str] = set()

    for page in range(1, max_pages + 1):
        payload = _kakao_get(
            "/v2/local/search/category.json",
            {
                "category_group_code": "FD6",
                "x": center_x,
                "y": center_y,
                "radius": radius,
                "sort": "distance",
                "size": 15,
                "page": page,
            },
            api_key,
        )

        for place in payload.get("documents", []):
            place_id = place.get("id")
            if not place_id or place_id in seen:
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
                    "distance_m": int(place.get("distance", "0")),
                    "phone": place.get("phone", ""),
                    "place_url": place.get("place_url", ""),
                    "rating": None,
                    "price": "정보없음",
                }
            )

        meta = payload.get("meta", {})
        if meta.get("is_end"):
            break

    return items
