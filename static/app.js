const spinBtn = document.getElementById("spinBtn");
const reel = document.getElementById("reel");
const resultCard = document.getElementById("resultCard");
const resultName = document.getElementById("resultName");
const resultMeta = document.getElementById("resultMeta");
const sourceText = document.getElementById("sourceText");
const noticeText = document.getElementById("noticeText");

const ITEM_HEIGHT = 90;
const REPEAT_COUNT = 20;
const apiUrl = window.API_URL || "./api/restaurants.php";
let spinning = false;
let restaurants = [];

function applyRestaurants(data) {
  restaurants = Array.isArray(data.restaurants) ? data.restaurants : [];
  if (sourceText) {
    sourceText.textContent = `데이터 소스: ${data.source || "알 수 없음"}`;
  }
  if (noticeText) {
    noticeText.textContent = data.notice || "";
  }
}

function buildReel() {
  if (restaurants.length === 0) {
    reel.innerHTML = '<div class="item">표시할 식당이 없습니다</div>';
    spinBtn.disabled = true;
    return;
  }

  const items = [];
  for (let i = 0; i < REPEAT_COUNT; i += 1) {
    for (const r of restaurants) {
      items.push(`<div class="item">${r.name}</div>`);
    }
  }
  reel.innerHTML = items.join("");
  spinBtn.disabled = false;
}

function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3);
}

function spin() {
  if (spinning || restaurants.length === 0) return;

  spinning = true;
  spinBtn.disabled = true;
  resultCard.classList.add("hidden");

  const winnerIndex = Math.floor(Math.random() * restaurants.length);
  const winner = restaurants[winnerIndex];

  const baseLoop = Math.floor(REPEAT_COUNT * 0.8);
  const targetPosition = (baseLoop * restaurants.length + winnerIndex) * ITEM_HEIGHT;
  const duration = 3200 + Math.random() * 700;
  const start = performance.now();

  function animate(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = easeOutCubic(progress);
    reel.style.transform = `translateY(${-targetPosition * eased}px)`;

    if (progress < 1) {
      requestAnimationFrame(animate);
      return;
    }

    resultName.textContent = `오늘의 추천: ${winner.name}`;

    const metaParts = [winner.category, winner.area];
    if (winner.distance_m !== undefined && winner.distance_m !== null) {
      metaParts.push(`${winner.distance_m}m`);
    }
    if (winner.price) {
      metaParts.push(winner.price);
    }
    if (winner.rating !== null && winner.rating !== undefined) {
      metaParts.push(`⭐ ${winner.rating}`);
    } else {
      metaParts.push("평점정보없음");
    }
    resultMeta.textContent = metaParts.join(" · ");

    resultCard.classList.remove("hidden");

    spinning = false;
    spinBtn.disabled = false;
  }

  requestAnimationFrame(animate);
}

async function loadRestaurants() {
  spinBtn.disabled = true;
  reel.innerHTML = '<div class="item">불러오는 중...</div>';

  try {
    const response = await fetch(apiUrl, { cache: "no-store" });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const data = await response.json();
    applyRestaurants(data);
  } catch (error) {
    const embedded = window.RESTAURANTS;
    if (Array.isArray(embedded) && embedded.length > 0) {
      applyRestaurants({
        source: "Flask 내장 데이터",
        notice: "PHP API 대신 Flask 렌더 데이터로 표시 중입니다.",
        restaurants: embedded,
      });
    } else {
      restaurants = [];
      if (sourceText) {
        sourceText.textContent = "데이터 소스: 로드 실패";
      }
      if (noticeText) {
        noticeText.textContent = `식당 데이터를 불러오지 못했습니다. (${error.message})`;
      }
    }
  }

  buildReel();
}

spinBtn.addEventListener("click", spin);
loadRestaurants();
