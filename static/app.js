const spinBtn = document.getElementById("spinBtn");
const reel = document.getElementById("reel");
const resultCard = document.getElementById("resultCard");
const resultName = document.getElementById("resultName");
const resultMeta = document.getElementById("resultMeta");
const mapLink = document.getElementById("mapLink");
const noticeText = document.getElementById("noticeText");
const locationBtn = document.getElementById("locationBtn");
const radiusRange = document.getElementById("radiusRange");
const radiusValue = document.getElementById("radiusValue");
const reloadBtn = document.getElementById("reloadBtn");
const locationStatus = document.getElementById("locationStatus");

const ITEM_HEIGHT = 90;
const REPEAT_COUNT = 20;
const apiUrl = window.API_URL || "./api/restaurants.php";
const defaultLocationLabel = window.DEFAULT_LOCATION_LABEL || "LS용산타워";
let spinning = false;
let restaurants = [];
let loading = false;
let userCoords = null;
let radiusKm = parseRadiusValue(radiusRange?.value || "0.5");

function parseRadiusValue(value) {
  const parsed = Number.parseFloat(value);
  if (!Number.isFinite(parsed)) return 0.5;
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

function applyRestaurants(data) {
  restaurants = Array.isArray(data.restaurants) ? data.restaurants : [];
  recommendationPool = [];
  if (noticeText) {
    noticeText.textContent = data.notice || "";
  }
  if (!userCoords && locationStatus && data.location) {
    locationStatus.textContent = `기본 위치 사용: ${data.location}`;
  }
}

function setNotice(message) {
  if (noticeText) {
    noticeText.textContent = message;
  }
}

function updateLocationUi() {
  if (locationBtn) {
    locationBtn.textContent = userCoords ? "📍 기본 위치로 되돌리기" : "📍 현재 위치 사용";
  }
  if (locationStatus) {
    const formattedRadius = formatRadiusText(radiusKm);
    if (userCoords) {
      locationStatus.textContent = `현재 위치 사용 중 (거리 ${formattedRadius})`;
    } else {
      locationStatus.textContent = `기본 위치 사용: ${defaultLocationLabel} (거리 ${formattedRadius})`;
    }
  }
}

function setLoading(state) {
  loading = state;
  if (reloadBtn) {
    reloadBtn.disabled = state;
  }
  if (locationBtn) {
    locationBtn.disabled = state;
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

function spin() {
  if (spinning || restaurants.length === 0) return;

  spinning = true;
  spinBtn.disabled = true;
  resultCard.classList.add("hidden");

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
    if (mapLink) {
      mapLink.href = getMapUrl(winner);
    }

    resultCard.classList.remove("hidden");

    spinning = false;
    spinBtn.disabled = false;
  }

  requestAnimationFrame(animate);
}

async function loadRestaurants() {
  setLoading(true);
  spinBtn.disabled = true;
  reel.innerHTML = '<div class="item">불러오는 중...</div>';

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
if (locationBtn) {
  locationBtn.addEventListener("click", () => {
    if (loading) return;

    if (userCoords) {
      userCoords = null;
      updateLocationUi();
      setNotice(`기본 위치(${defaultLocationLabel})로 전환했습니다.`);
      loadRestaurants();
      return;
    }

    requestCurrentLocation();
  });
}

updateRadiusDisplay();
updateLocationUi();
requestCurrentLocation(true);
