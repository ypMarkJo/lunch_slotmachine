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
$cheerApiPath = $basePath . "/api/cheer.php";

// 누적 방문자 수 파일 카운터
$counterFile = __DIR__ . "/counter.txt";
$visitCount = 1;
if (file_exists($counterFile)) {
    $visitCount = (int)file_get_contents($counterFile) + 1;
}
@file_put_contents($counterFile, (string)$visitCount, LOCK_EX);
$formattedVisits = number_format($visitCount);
?>
<!doctype html>
<html lang="ko">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>점심 빠칭코 추천</title>
    <link rel="manifest" href="manifest.json" />
    <meta name="theme-color" content="#0f172a" />
    <link rel="apple-touch-icon" href="static/icon-192.png" />
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
        <button id="pwaInstallBtn" class="pwa-install-btn hidden" type="button">📲 앱으로 설치하기</button>
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
              <option value="900" selected>1500 페이스 (성인 보통걸음 🚶)</option>
              <option value="720">1200 페이스 (빠른걸음 🚶‍♂️)</option>
              <option value="600">1000 페이스 (축지법 🏃‍♀️)</option>
              <option value="480">800 페이스 (가벼운조깅 🏃)</option>
              <option value="420">700 페이스 (러닝 🏃‍♂️)</option>
              <option value="360">600 페이스 (맹렬질주 ⚡)</option>
              <option value="240">400 페이스 (전력질주 🔥)</option>
              <option value="180">300 페이스 (국가대표 🏆)</option>
            </select>
          </div>
          <div id="resultTimeBadge" class="time-badge">
            <span class="time-label">예상 소요시간</span>
            <span id="timeValue" class="time-value"></span>
          </div>
          <div id="adArea" class="ad-area"></div>
          <a id="mapLink" class="map-link" href="#" target="_blank" rel="noopener noreferrer">가게 지도 보기 🗺️</a>
        </section>
      </div>

      <footer class="app-footer">
        <div class="footer-content">
          <div class="footer-row">
            <span class="visitor-badge">🎰 누군가의 점심을 <span class="visitor-count"><?= $formattedVisits ?></span>번째 추천 중!</span>
          </div>
          <div class="footer-row cheer-row">
            <button id="openCheerBtn" class="cheer-trigger-btn" type="button">☕ 개발자 응원하기</button>
          </div>
        </div>
      </footer>
    </main>

    <!-- iOS 설치 안내 모달 -->
    <div id="iosInstallModalOverlay" class="modal-overlay hidden">
      <section class="result-modal cheer-modal pwa-guide-modal">
        <button id="closeIosInstallModalBtn" class="close-modal-btn" type="button" aria-label="닫기">✕</button>
        <h2 class="cheer-modal-title">📲 바탕화면에 앱으로 추가하기</h2>
        <p class="cheer-modal-sub">사용 중이신 브라우저에 따라 홈 화면에 앱을 추가해 보세요!</p>
        <div class="pwa-guide-steps">
          <div class="pwa-step-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
            <div style="font-weight: 800; color: #ffd166;">🧭 Safari 브라우저 이용 시</div>
            <div>하단 중앙의 <strong>[공유 ⎋]</strong> 버튼 ➔ <strong>[홈 화면에 추가 (+)]</strong> 선택</div>
          </div>
          <div class="pwa-step-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
            <div style="font-weight: 800; color: #60a5fa;">🌐 Chrome 브라우저 이용 시</div>
            <div>우측 상단 <strong>[공유 ⎋]</strong> 버튼 ➔ <strong>[홈 화면에 추가 (+)]</strong> 선택</div>
          </div>
        </div>
      </section>
    </div>

    <!-- 개발자 응원 모달 -->
    <div id="cheerModalOverlay" class="modal-overlay hidden">
      <section class="result-modal cheer-modal">
        <button id="closeCheerModalBtn" class="close-modal-btn" type="button" aria-label="닫기">✕</button>
        <h2 class="cheer-modal-title">🎁 개발자 응원하기</h2>
        <p class="cheer-modal-sub">따뜻한 한마디나 커피/기프티콘을 자유롭게 전해주세요!</p>

        <form id="cheerForm" class="cheer-form">
          <textarea id="cheerMsgInput" class="cheer-textarea" placeholder="개발자에게 전하고 싶은 따뜻한 한마디를 적어주세요! 💌" rows="4"></textarea>

          <div class="file-upload-box">
            <label for="gifticonInput" class="file-upload-label">
              <span class="file-icon">🖼️</span>
              <span id="fileNameDisplay" class="file-name">기프티콘 이미지 첨부하기 (선택)</span>
            </label>
            <input id="gifticonInput" type="file" accept="image/*" class="file-input" />
          </div>

          <button id="sendCheerMsgBtn" class="spin-btn send-cheer-btn" type="submit">❤️ 마음 전하기</button>
        </form>
      </section>
    </div>

    <script>
      window.API_URL = <?= json_encode($apiPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.CHEER_API_URL = <?= json_encode($cheerApiPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.DEFAULT_LOCATION_LABEL = "LS용산타워";
    </script>
    <script src="<?= htmlspecialchars($appJsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
  </body>
</html>
