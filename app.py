import os
from pathlib import Path

from flask import Flask, render_template

from data.kakao_places import fetch_nearby_restaurants
from data.restaurants import RESTAURANTS

app = Flask(__name__)


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


@app.get("/")
def index():
    api_key = os.getenv("KAKAO_REST_API_KEY")
    source = "샘플 데이터"
    notice = "KAKAO_REST_API_KEY 미설정으로 샘플 데이터를 표시 중입니다."
    restaurants = RESTAURANTS

    if api_key:
        try:
            restaurants = fetch_nearby_restaurants(api_key=api_key, radius=2000, max_pages=3)
            source = "카카오 로컬 API"
            notice = "LS용산타워 반경 2km 식당 데이터를 표시 중입니다. (평점 데이터는 카카오 로컬 API 미제공)"
            if not restaurants:
                restaurants = RESTAURANTS
                source = "샘플 데이터"
                notice = "카카오 조회 결과가 비어 샘플 데이터로 대체했습니다."
        except RuntimeError as error:
            restaurants = RESTAURANTS
            source = "샘플 데이터"
            notice = f"카카오 조회 실패로 샘플 데이터로 대체했습니다. ({error})"

    return render_template(
        "index.html",
        restaurants=restaurants,
        source=source,
        notice=notice,
    )


if __name__ == "__main__":
    app.run(debug=True)
