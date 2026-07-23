<?php
$scriptName = $_SERVER["SCRIPT_NAME"] ?? "/index.php";
$basePath = rtrim(str_replace("\\", "/", dirname($scriptName)), "/");
$basePath = $basePath === "/" ? "" : $basePath;
$stylePath = $basePath . "/static/style.css";
$appJsPath = $basePath . "/static/app.js";
$apiPath = $basePath . "/api/restaurants.php";
?>
<!doctype html>
<html lang="ko">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>점심 빠칭코 추천</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>" />
  </head>
  <body>
    <main class="container">
      <h1>점심 메뉴 빠칭코</h1>
      <p class="sub">버튼을 누르면 랜덤 맛집이 빠칭코처럼 돌아가며 멈춥니다.</p>
      <p id="sourceText" class="source">데이터 소스: 로딩 중...</p>
      <p id="noticeText" class="notice">식당 정보를 불러오는 중입니다.</p>
      <p class="guide-tip">💡 <strong>사용 방법:</strong> [거리 슬라이더 조절] → [주변 식당 불러오기] → [추천 시작(빠칭코)]</p>
      <section class="controls">
        <button id="locationBtn" class="control-btn" type="button">현재 위치 사용</button>
        <div class="radius-control">
          <label class="radius-label" for="radiusRange">거리: <span id="radiusValue">2.0km</span></label>
          <input id="radiusRange" type="range" min="0.1" max="5.0" step="0.1" value="2.0" class="radius-slider" />
        </div>
        <button id="reloadBtn" class="control-btn secondary" type="button">주변 식당 불러오기</button>
      </section>
      <p id="locationStatus" class="location-status">기본 위치 사용: LS용산타워</p>

      <section class="machine">
        <div class="pointer"></div>
        <div class="window">
          <div id="reel" class="reel"></div>
        </div>
      </section>

      <button id="spinBtn" class="spin-btn">추천 시작</button>

      <section id="resultCard" class="result hidden">
        <h2 id="resultName"></h2>
        <p id="resultMeta"></p>
        <a id="mapLink" class="map-link" href="#" target="_blank" rel="noopener noreferrer">가게 보기</a>
      </section>
    </main>

    <script>
      window.API_URL = <?= json_encode($apiPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.DEFAULT_LOCATION_LABEL = "LS용산타워";
    </script>
    <script src="<?= htmlspecialchars($appJsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
  </body>
</html>
