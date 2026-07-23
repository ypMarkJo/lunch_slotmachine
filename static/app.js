const spinBtn = document.getElementById("spinBtn");
const reel = document.getElementById("reel");
const resultCard = document.getElementById("resultCard");
const resultName = document.getElementById("resultName");
const resultMeta = document.getElementById("resultMeta");
const sourceText = document.getElementById("sourceText");
const noticeText = document.getElementById("noticeText");
const locationBtn = document.getElementById("locationBtn");
const radiusSelect = document.getElementById("radiusSelect");
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
let radiusKm = Number.parseInt(radiusSelect?.value || "2", 10) || 2;

function applyRestaurants(data) {
  restaurants = Array.isArray(data.restaurants) ? data.restaurants : [];
  if (sourceText) {
    sourceText.textContent = `데이터 소스: ${data.source || "알 수 없음"}`;
  }
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

function clampRadius(value) {
  if (!Number.isFinite(value)) return 2;
  return Math.min(5, Math.max(1, Math.round(value)));
}

function updateLocationUi() {
  if (locationBtn) {
    locationBtn.textContent = userCoords ? "기본 위치로 되돌리기" : "현재 위치 사용";
  }
  if (locationStatus) {
    if (userCoords) {
      locationStatus.textContent = `현재 위치 사용 중 (반경 ${radiusKm}km)`;
    } else {
      locationStatus.textContent = `기본 위치 사용: ${defaultLocationLabel} (반경 ${radiusKm}km)`;
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
  if (radiusSelect) {
    radiusSelect.disabled = state;
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
      if (sourceText) {
        sourceText.textContent = "데이터 소스: 로드 실패";
      }
      setNotice(`식당 데이터를 불러오지 못했습니다. (${error.message})`);
    }
  }

  buildReel();
  updateLocationUi();
  setLoading(false);
}

function requestCurrentLocation() {
  if (!navigator.geolocation) {
    setNotice("브라우저가 위치 정보를 지원하지 않아 기본 위치로 조회합니다.");
    return;
  }

  setLoading(true);
  setNotice("현재 위치를 가져오는 중입니다...");

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
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
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
if (radiusSelect) {
  radiusSelect.addEventListener("change", () => {
    radiusKm = clampRadius(Number.parseInt(radiusSelect.value, 10));
    updateLocationUi();
    if (!loading) {
      loadRestaurants();
    }
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

updateLocationUi();
loadRestaurants();
