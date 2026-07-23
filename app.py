import os
from pathlib import Path
from typing import Optional

from flask import Flask, jsonify, render_template, request

from data.kakao_places import fetch_nearby_restaurants
from data.restaurants import RESTAURANTS

app = Flask(__name__)
DEFAULT_LOCATION_LABEL = "LS용산타워"


def load_dotenv(path: str = ".env") -> None:
    env_path = Path(path)
    if not env_path.exists():
        return

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip().strip('"').strip("'")

        if key:
            os.environ.setdefault(key, value)


load_dotenv()


def parse_radius_km(raw: Optional[str], default: float = 2.0) -> float:
    try:
        value = float(raw) if raw is not None else default
    except ValueError:
        value = default
    return max(0.1, min(5.0, round(value, 1)))


def parse_coordinate(raw: Optional[str], minimum: float, maximum: float) -> Optional[float]:
    if raw is None:
        return None
    try:
        value = float(raw)
    except ValueError:
        return None
    if value < minimum or value > maximum:
        return None
    return value


def determine_max_pages(radius_km: float) -> int:
    # 카카오 API 최대 지원 페이지 수(45페이지 = 675개 후보)까지 요청하여 가능한 한 최대 100개 이상 식당을 수집합니다.
    return 45


def build_restaurant_payload(
    radius_km: float, latitude: Optional[float] = None, longitude: Optional[float] = None
) -> dict:
    api_key = os.getenv("KAKAO_REST_API_KEY")
    using_current_location = latitude is not None and longitude is not None
    location_label = "현재 위치" if using_current_location else DEFAULT_LOCATION_LABEL

    source = "샘플 데이터"
    notice = f"KAKAO_REST_API_KEY 미설정으로 샘플 데이터를 표시 중입니다. (반경 {radius_km}km)"
    restaurants = RESTAURANTS

    if api_key:
        try:
            restaurants = fetch_nearby_restaurants(
                api_key=api_key,
                radius=int(round(radius_km * 1000)),
                max_pages=determine_max_pages(radius_km),
                latitude=latitude,
                longitude=longitude,
            )
            source = "카카오 로컬 API"
            notice = f"{location_label} 반경 {radius_km}km 식당 데이터를 표시 중입니다. (평점 데이터는 카카오 로컬 API 미제공)"
            if not restaurants:
                restaurants = RESTAURANTS
                source = "샘플 데이터"
                notice = f"{location_label} 반경 {radius_km}km 조회 결과가 비어 샘플 데이터로 대체했습니다."
        except RuntimeError as error:
            restaurants = RESTAURANTS
            source = "샘플 데이터"
            notice = f"카카오 조회 실패로 샘플 데이터로 대체했습니다. ({error})"

    return {
        "restaurants": restaurants,
        "source": source,
        "notice": notice,
        "location": location_label,
    }


@app.get("/")
def index():
    payload = build_restaurant_payload(radius_km=0.1)
    return render_template(
        "index.html",
        restaurants=payload["restaurants"],
        source=payload["source"],
        notice=payload["notice"],
    )


@app.get("/api/restaurants")
def api_restaurants():
    radius_km = parse_radius_km(request.args.get("radius_km"), default=0.1)
    latitude = parse_coordinate(request.args.get("lat"), minimum=-90.0, maximum=90.0)
    longitude = parse_coordinate(request.args.get("lng"), minimum=-180.0, maximum=180.0)
    payload = build_restaurant_payload(radius_km=radius_km, latitude=latitude, longitude=longitude)
    return jsonify(payload)


if __name__ == "__main__":
    app.run(debug=True)
