const spinBtn = document.getElementById("spinBtn");
const reel = document.getElementById("reel");
const resultCard = document.getElementById("resultCard");
const resultName = document.getElementById("resultName");
const resultMeta = document.getElementById("resultMeta");
const mapLink = document.getElementById("mapLink");
const radiusRange = document.getElementById("radiusRange");
const radiusValue = document.getElementById("radiusValue");
const reloadBtn = document.getElementById("reloadBtn");
const categorySelect = document.getElementById("categorySelect");
const modalOverlay = document.getElementById("modalOverlay");
const closeModalBtn = document.getElementById("closeModalBtn");

const ITEM_HEIGHT = 90;
const REPEAT_COUNT = 20;
const apiUrl = window.API_URL || "./api/restaurants.php";
const defaultLocationLabel = window.DEFAULT_LOCATION_LABEL || "LS용산타워";
let spinning = false;
let rawRestaurants = [];
let restaurants = [];
let loading = false;
let userCoords = null;
let radiusKm = parseRadiusValue(radiusRange?.value || "0.1");
let selectedCategory = "ALL";

function parseRadiusValue(value) {
  const parsed = Number.parseFloat(value);
  if (!Number.isFinite(parsed)) return 0.1;
  return Math.min(5.0, Math.max(0.1, Math.round(parsed * 10) / 10));
}

function formatRadiusText(km) {
  if (km < 1) {
    return `${Math.round(km * 1000)}m`;
  }
  return `${km.toFixed(1)}km`;
}

function updateRadiusDisplay() {
  if (radiusValue) {
    radiusValue.textContent = formatRadiusText(radiusKm);
  }
}

function filterRestaurants() {
  if (!selectedCategory || selectedCategory === "ALL") {
    restaurants = [...rawRestaurants];
    return;
  }

  restaurants = rawRestaurants.filter((r) => {
    const cat = r.category || "";
    const catName = r.category_name || "";
    return cat.includes(selectedCategory) || catName.includes(selectedCategory);
  });
}

function applyRestaurants(data) {
  rawRestaurants = Array.isArray(data.restaurants) ? data.restaurants : [];
  filterRestaurants();
  recommendationPool = [];
}

function setNotice(message) {
  // notice text removed as requested
}

function updateLocationUi() {
  // locationStatus text removed as per design request
}

function setLoading(state) {
  loading = state;
  if (reloadBtn) {
    reloadBtn.disabled = state;
  }
  if (radiusRange) {
    radiusRange.disabled = state;
  }
}

function buildApiUrl() {
  const requestUrl = new URL(apiUrl, window.location.href);
  requestUrl.searchParams.set("radius_km", String(radiusKm));
  if (userCoords) {
    requestUrl.searchParams.set("lat", String(userCoords.lat));
    requestUrl.searchParams.set("lng", String(userCoords.lng));
  }
  return requestUrl.toString();
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
  spinBtn.disabled = loading;
}

function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3);
}

function getNextWinnerIndex() {
  if (restaurants.length === 0) return -1;

  if (recommendationPool.length === 0) {
    recommendationPool = Array.from({ length: restaurants.length }, (_, index) => index);
    for (let i = recommendationPool.length - 1; i > 0; i -= 1) {
      const j = Math.floor(Math.random() * (i + 1));
      [recommendationPool[i], recommendationPool[j]] = [recommendationPool[j], recommendationPool[i]];
    }
  }

  const nextIndex = recommendationPool.pop();
  return nextIndex === undefined ? -1 : nextIndex;
}

function getMapUrl(restaurant) {
  if (restaurant.place_url) {
    return restaurant.place_url;
  }

  const query = [restaurant.name, restaurant.area].filter(Boolean).join(" ");
  return `https://map.kakao.com/link/search/${encodeURIComponent(query)}`;
}

function hideModal() {
  if (modalOverlay) {
    modalOverlay.classList.add("hidden");
  }
}

function showModal() {
  if (modalOverlay) {
    modalOverlay.classList.remove("hidden");
  }
}

function spin() {
  if (spinning || restaurants.length === 0) return;

  spinning = true;
  spinBtn.disabled = true;
  hideModal();

  const winnerIndex = getNextWinnerIndex();
  if (winnerIndex < 0) return;
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

    const metaParts = [winner.category];
    if (winner.distance_m !== undefined && winner.distance_m !== null) {
      const walkMins = Math.max(1, Math.round(winner.distance_m / 67));
      metaParts.push(`🚶‍♂️ 도보 약 ${walkMins}분 (${winner.distance_m}m)`);
    }
    if (winner.area) {
      metaParts.push(winner.area);
    }
    if (winner.price && winner.price !== "정보없음") {
      metaParts.push(winner.price);
    }
    if (winner.rating !== null && winner.rating !== undefined) {
      metaParts.push(`⭐ ${winner.rating}`);
    }
    resultMeta.textContent = metaParts.join(" · ");
    if (mapLink) {
      mapLink.href = getMapUrl(winner);
    }

    showModal();

    spinning = false;
    spinBtn.disabled = false;
  }

  requestAnimationFrame(animate);
}

async function loadRestaurants() {
  setLoading(true);
  spinBtn.disabled = true;
  hideModal();
  if (reel) {
    reel.style.transform = "translateY(0)";
    reel.innerHTML = '<div class="item">불러오는 중...</div>';
  }

  try {
    const response = await fetch(buildApiUrl(), { cache: "no-store" });
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
        notice: "API 호출 실패로 내장 샘플 데이터를 표시 중입니다. 위치/반경 변경은 API 실행 시 사용할 수 있습니다.",
        location: defaultLocationLabel,
        restaurants: embedded,
      });
    } else {
      restaurants = [];
      setNotice(`식당 데이터를 불러오지 못했습니다. (${error.message})`);
    }
  }

  setLoading(false);
  buildReel();
  updateLocationUi();
}

function requestCurrentLocation(autoLoadOnFail = false) {
  if (!navigator.geolocation) {
    setNotice("브라우저가 위치 정보를 지원하지 않아 기본 위치로 조회합니다.");
    if (autoLoadOnFail) {
      loadRestaurants();
    }
    return;
  }

  setLoading(true);
  setNotice("현재 위치를 확인하는 중입니다...");

  navigator.geolocation.getCurrentPosition(
    (position) => {
      userCoords = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      };
      updateLocationUi();
      loadRestaurants();
    },
    (error) => {
      userCoords = null;
      setLoading(false);
      updateLocationUi();
      switch (error.code) {
        case error.PERMISSION_DENIED:
          setNotice("위치 권한이 거부되어 기본 위치로 조회합니다.");
          break;
        case error.TIMEOUT:
          setNotice("위치 확인 시간이 초과되어 기본 위치로 조회합니다.");
          break;
        default:
          setNotice("현재 위치를 확인하지 못해 기본 위치로 조회합니다.");
          break;
      }
      if (autoLoadOnFail) {
        loadRestaurants();
      }
    },
    {
      enableHighAccuracy: true,
      timeout: 7000,
      maximumAge: 60000,
    }
  );
}

spinBtn.addEventListener("click", spin);
if (reloadBtn) {
  reloadBtn.addEventListener("click", () => {
    if (!loading) {
      loadRestaurants();
    }
  });
}
if (radiusRange) {
  radiusRange.addEventListener("input", () => {
    radiusKm = parseRadiusValue(radiusRange.value);
    updateRadiusDisplay();
    updateLocationUi();
  });
  radiusRange.addEventListener("change", () => {
    radiusKm = parseRadiusValue(radiusRange.value);
    updateRadiusDisplay();
    updateLocationUi();
    setNotice("거리 설정이 변경되었습니다. '주변 식당 불러오기' 버튼을 눌러 목록을 갱신하세요.");
  });
}
if (categorySelect) {
  categorySelect.addEventListener("change", () => {
    selectedCategory = categorySelect.value;
    filterRestaurants();
    recommendationPool = [];
    buildReel();
    hideModal();
  });
}
if (closeModalBtn) {
  closeModalBtn.addEventListener("click", hideModal);
}
if (modalOverlay) {
  modalOverlay.addEventListener("click", (e) => {
    if (e.target === modalOverlay) {
      hideModal();
    }
  });
}

updateRadiusDisplay();
updateLocationUi();
requestCurrentLocation(true);
