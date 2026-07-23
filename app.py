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


@app.post("/api/cheer")
def api_cheer():
    message = request.form.get("message", "").strip()
    gifticon_file = request.files.get("gifticon")

    if not message and not gifticon_file:
        return jsonify({"success": False, "error": "응원 메시지나 기프티콘 이미지를 첨부해 주세요."}), 400

    bot_token = os.getenv("TELEGRAM_BOT_TOKEN")
    chat_id = os.getenv("TELEGRAM_CHAT_ID")

    if not bot_token or not chat_id:
        return jsonify({"success": True, "notice": "텔레그램 환경변수 미설정으로 기록만 수행되었습니다."})

    try:
        import uuid
        import urllib.request
        import json

        caption = f"🎁 [점심뭐묵칭코 개발자 응원]\n\n{message or '(메시지 없음)'}"

        if gifticon_file:
            boundary = f"----WebKitFormBoundary{uuid.uuid4().hex}"
            body = []

            # chat_id
            body.append(f"--{boundary}".encode("utf-8"))
            body.append(b'Content-Disposition: form-data; name="chat_id"\r\n')
            body.append(chat_id.encode("utf-8"))

            # caption
            body.append(f"--{boundary}".encode("utf-8"))
            body.append(b'Content-Disposition: form-data; name="caption"\r\n')
            body.append(caption.encode("utf-8"))

            # photo file
            filename = gifticon_file.filename or "gifticon.jpg"
            content_type = gifticon_file.mimetype or "image/jpeg"
            file_bytes = gifticon_file.read()

            body.append(f"--{boundary}".encode("utf-8"))
            body.append(f'Content-Disposition: form-data; name="photo"; filename="{filename}"\r\nContent-Type: {content_type}\r\n'.encode("utf-8"))
            body.append(file_bytes)

            body.append(f"--{boundary}--\r\n".encode("utf-8"))

            payload_bytes = b"\r\n".join(body)
            url = f"https://api.telegram.org/bot{bot_token}/sendPhoto"
            headers = {"Content-Type": f"multipart/form-data; boundary={boundary}"}
        else:
            url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
            payload_bytes = json.dumps({"chat_id": chat_id, "text": caption}).encode("utf-8")
            headers = {"Content-Type": "application/json"}

        req = urllib.request.Request(url, data=payload_bytes, headers=headers)
        with urllib.request.urlopen(req, timeout=15) as resp:
            if resp.status == 200:
                return jsonify({"success": True})
        return jsonify({"success": False, "error": "전송 실패"}), 500
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.get("/api/restaurants")
def api_restaurants():
    radius_km = parse_radius_km(request.args.get("radius_km"), default=0.1)
    latitude = parse_coordinate(request.args.get("lat"), minimum=-90.0, maximum=90.0)
    longitude = parse_coordinate(request.args.get("lng"), minimum=-180.0, maximum=180.0)
    payload = build_restaurant_payload(radius_km=radius_km, latitude=latitude, longitude=longitude)
    return jsonify(payload)


if __name__ == "__main__":
    app.run(debug=True)
