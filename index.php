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
      </section>
    </main>

    <script>
      window.API_URL = <?= json_encode($apiPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= htmlspecialchars($appJsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
  </body>
</html>
