# lunch_slotmachine

카카오 로컬 API(또는 샘플 데이터)를 사용해 점심 식당을 슬롯머신 UI로 랜덤 추천하는 웹 앱입니다.

## 주요 기능

- **슬롯머신 애니메이션 추천 UI**
- **현재 위치 기반 추천**: 브라우저 위치 권한으로 현재 위치 주변 식당 조회
- **세밀한 거리 선택 게이지 (슬라이더)**: 100m ~ 5.0km 범위에서 0.1km 단위로 조회 거리 설정
- **단계별 가이드 UI**: [거리 선택] → [주변 식당 불러오기] → [추천 시작(빠칭코)] 명확한 UX 안내
- **카카오 로컬 API 연동 & 최대 200개 수집**: 다방향 격자 검색으로 가능한 경우 최대 200개 맛집 데이터 확보
- **자동 폴백**: API 키 미설정/조회 실패 시 샘플 데이터로 자동 전환
- **이중 실행 방식 지원**: PHP 기반 실행, Flask 기반 실행

## 프로젝트 구조

| 경로 | 설명 |
| --- | --- |
| `index.php` | PHP 진입 페이지 |
| `api/restaurants.php` | 카카오 API 조회 + JSON 응답 API |
| `app.py` | Flask 진입점 |
| `templates/index.html` | Flask 렌더 템플릿 |
| `static/app.js` | 슬롯머신 동작/데이터 로딩 |
| `static/style.css` | UI 스타일 |
| `data/sample_restaurants.php` | PHP용 샘플 식당 데이터 |
| `data/restaurants.py` | Flask용 샘플 식당 데이터 |
| `data/kakao_places.py` | Flask용 카카오 API 연동 로직 |

## 사전 준비

1. 카카오 Developers에서 REST API 키 발급
2. 루트에 `.env` 파일 생성

```env
KAKAO_REST_API_KEY=여기에_카카오_REST_API_KEY
```

> 키가 없거나 조회에 실패하면 샘플 데이터로 동작합니다.

## 실행 방법

### 1) PHP로 실행

PHP 내장 서버 예시:

```bash
php -S localhost:8000
```

브라우저에서 `http://localhost:8000` 접속

### 2) Flask로 실행

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python app.py
```

브라우저에서 `http://127.0.0.1:5000` 접속

## 데이터 동작 방식

- **PHP 경로**: `static/app.js` → `api/restaurants.php` 호출
- **Flask 경로**: `static/app.js` → `/api/restaurants` 호출 (실패 시 템플릿 내장 데이터 폴백)
- **조회 파라미터**: `radius_km=0.1~5.0`, `lat`, `lng` (현재 위치 사용 시 자동 포함)
- **수집 방식**: 중심점 + 8방향 보정 서치를 사용하여 구역 내 식당을 최대 200개까지 확보 (식당이 적은 100m~500m 구간은 실제 존재하는 최대 개수 자동 수집)
- **위치 폴백**: 현재 위치 미선택/권한 거부 시 `LS용산타워` 기준 조회
- 카카오 로컬 API는 평점 정보를 제공하지 않아, API 데이터 사용 시 평점은 `null`(UI에서는 `평점정보없음`)로 표시됩니다.
