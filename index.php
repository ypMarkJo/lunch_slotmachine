<?php
$scriptName = $_SERVER["SCRIPT_NAME"] ?? "/index.php";
$basePath = rtrim(str_replace("\\", "/", dirname($scriptName)), "/");
$basePath = $basePath === "/" ? "" : $basePath;
$styleFile = __DIR__ . "/static/style.css";
$appJsFile = __DIR__ . "/static/app.js";
$styleVer = file_exists($styleFile) ? filemtime($styleFile) : time();
$appJsVer = file_exists($appJsFile) ? filemtime($appJsFile) : time();
$stylePath = $basePath . "/static/style.css?v=" . $styleVer;
$appJsPath = $basePath . "/static/app.js?v=" . $appJsVer;
$apiPath = $basePath . "/api/restaurants.php";
?>
<!doctype html>
<html lang="ko">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>점심 빠칭코 추천</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Do+Hyeon&family=Noto+Sans+KR:wght@700;800;900&family=Outfit:wght@700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>" />
  </head>
  <body>
    <main class="container">
      <header class="app-header">
        <div class="brand-badge">🎰 LUNCH SLOT</div>
        <h1 class="app-title">점심 뭐묵칭코</h1>
      </header>
      <p class="guide-tip">💡 <strong>사용 방법:</strong> [거리 슬라이더 조절] → [주변 식당 불러오기] → [추천 시작(빠칭코)]</p>
      <section class="controls">
        <div class="radius-control">
          <label class="radius-label" for="radiusRange">최대거리: <span id="radiusValue">100m</span></label>
          <input id="radiusRange" type="range" min="0.1" max="5.0" step="0.1" value="0.1" class="radius-slider" />
        </div>
        <div class="category-control">
          <label class="category-label" for="categorySelect">카테고리</label>
          <select id="categorySelect" class="category-select">
            <option value="ALL">전체 음식점</option>
            <option value="한식">한식 🍚</option>
            <option value="양식">양식 🍝</option>
            <option value="일식">일식 🍣</option>
            <option value="중식">중식 🥟</option>
            <option value="분식">분식 떡볶이</option>
            <option value="패스트푸드">패스트푸드 🍔</option>
            <option value="카페">카페/디저트 ☕</option>
          </select>
        </div>
        <button id="reloadBtn" class="control-btn secondary" type="button">🔄 주변 식당 불러오기</button>
      </section>

      <section class="machine">
        <div class="window">
          <div id="reel" class="reel"></div>
        </div>
      </section>

      <button id="spinBtn" class="spin-btn">추천 시작</button>

      <div id="modalOverlay" class="modal-overlay hidden">
        <section id="resultCard" class="result-modal">
          <button id="closeModalBtn" class="close-modal-btn" type="button" aria-label="닫기">✕</button>
          <h2 id="resultName"></h2>
          <p id="resultMeta" class="result-info"></p>
          <div class="pace-control modal-pace">
            <label class="pace-label" for="paceSelect">1km 페이스 선택 🏃‍♂️</label>
            <select id="paceSelect" class="pace-select">
              <option value="900" selected>1500 페이스 (15분/km, 성인 보통걸음 🚶)</option>
              <option value="720">1200 페이스 (12분/km, 빠른걸음 🚶‍♂️)</option>
              <option value="600">1000 페이스 (10분/km, 축지법 🏃‍♀️)</option>
              <option value="480">800 페이스 (8분/km, 가벼운조깅 🏃)</option>
              <option value="420">700 페이스 (7분/km, 러닝 🏃‍♂️)</option>
              <option value="360">600 페이스 (6분/km, 맹렬질주 ⚡)</option>
              <option value="240">400 페이스 (4분/km, 전력질주 🔥)</option>
              <option value="180">300 페이스 (3분/km, 국가대표 🏆)</option>
            </select>
          </div>
          <div id="resultTimeBadge" class="time-badge">
            <span class="time-label">예상 소요시간</span>
            <span id="timeValue" class="time-value"></span>
          </div>
          <div id="adArea" class="ad-area">
            <ins class="kakao_ad_area" style="display:none;"
                 data-ad-unit="DAN-ZDwpKOGHrs5WVYZK"
                 data-ad-width="320"
                 data-ad-height="50"></ins>
            <script type="text/javascript" src="//t1.kakaocdn.net/kas/static/ba.min.js" async></script>
          </div>
          <a id="mapLink" class="map-link" href="#" target="_blank" rel="noopener noreferrer">가게 지도 보기 🗺️</a>
        </section>
      </div>
    </main>

    <script>
      window.API_URL = <?= json_encode($apiPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.DEFAULT_LOCATION_LABEL = "LS용산타워";
    </script>
    <script src="<?= htmlspecialchars($appJsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
  </body>
</html>
