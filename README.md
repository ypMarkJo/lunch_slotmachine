# lunch_slotmachine (점심 뭐묵칭코 🎰)

카카오 로컬 API(또는 샘플 데이터)를 사용해 현재 위치 주변 식당을 빠칭코 슬롯머신 UI로 위트 있게 추천하는 웹 애플리케이션입니다.

## 주요 기능

- **🎰 슬롯머신 애니메이션 추천 UI**: 빠칭코 방식의 릴 회전 애니메이션으로 점심 메뉴 흥미 유발
- **📍 현재 위치 기반 자동 조회**: 브라우저 지오로케이션(Geolocation)을 이용해 내 주변 식당 실시간 탐색 (미허용 시 LS용산타워 자동 폴백)
- **📏 거리 조절 게이지 (100m ~ 5.0km)**: 0.1km 단위 슬라이더로 탐색 범위 조절 (기본값 100m)
- **🍱 카테고리 필터링**: 전체 / 한식 🍚 / 양식 🍝 / 일식 🍣 / 중식 🥟 / 분식 떡볶이 / 패스트푸드 🍔 / 카페·디저트 ☕ 선택 시 실시간 릴 재구성
- **🏃‍♂️ 1km 페이스별 도보/러닝 소요시간 계산**:
  - 팝업 결과창 내 300~1500 페이스(15분/km~3분/km) 선택에 따른 예상 소요시간 정밀 재계산 (기본값: 성인 보통걸음 1500 페이스)
- **🎁 당첨 모달 팝업 & 카카오 애드핏 광고**:
  - 결과 당첨 시 백드롭 모달 팝업 출현
  - 팝업 내 카카오 애드핏(Kakao AdFit 320x50) 수익화 배너 광고 슬롯 완비
- **👀 실시간 파일 기반 누적 방문자 카운터**:
  - `🎰 누군가의 점심을 N번째 추천 중!` 푸터 뱃지 (PHP `counter.txt` 파일 동기화)
- **⚡ 다방향 격자 탐색 (최대 200개 데이터)**: 중심점 + 4~8방향 보정 서치로 빠른 응답 속도(0.2~1.6s) 및 풍부한 식당 데이터 확보

---

## 프로젝트 구조

| 경로 | 설명 |
| --- | --- |
| `index.php` | PHP 메인 진입점 (카운터, 캐시 버스터, 카카오 애드핏 배너) |
| `api/restaurants.php` | 카카오 API 조회 + category_name / distance_m 응답 API |
| `app.py` | Flask 진입점 |
| `templates/index.html` | Flask 렌더 템플릿 |
| `static/app.js` | 슬롯머신 로직, 모달 팝업, 페이스 소요시간 재계산, 실시간 필터 |
| `static/style.css` | 3D 커스텀 타이틀, 모달 팝업, 뱃지 및 모바일 대응 CSS |
| `counter.txt` | PHP 기반 누적 방문자 수 기록 파일 |
| `data/sample_restaurants.php` | PHP용 샘플 식당 데이터 |
| `data/restaurants.py` | Flask용 샘플 식당 데이터 |
| `data/kakao_places.py` | Flask용 카카오 API 연동 및 다방향 탐색 로직 |

---

## 사전 준비

1. 카카오 Developers에서 REST API 키 발급
2. 프로젝트 루트에 `.env` 파일 생성

```env
KAKAO_REST_API_KEY=여기에_카카오_REST_API_KEY
```

> API 키가 없거나 조회 실패 시 내장 샘플 데이터로 자동 작동합니다.

---

## 실행 방법

### 1) PHP 실행 (추천)

PHP 내장 서버 실행:

```bash
php -S localhost:8000
```

브라우저에서 `http://localhost:8000` 접속

### 2) Flask 실행

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python app.py
```

브라우저에서 `http://127.0.0.1:5000` 접속

---

## 데이터 및 배포 관리

- **FTP 배포 업로드 핵심 파일**: `index.php`, `static/style.css`, `static/app.js`
- **웹 폰트**: `Do Hyeon`, `Outfit`, `Black Han Sans` Google Fonts 연동
- **캐시 버스팅**: `index.php`에서 `filemtime` 기반 `?v=timestamp`를 부여하여 브라우저 CSS/JS 구버전 캐싱 방지
