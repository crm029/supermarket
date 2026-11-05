(() => {
  "use strict";
  const loader = document.currentScript;
  const measurementId = String(loader?.dataset?.ga4Measurement || "").toUpperCase();
  if (!/^G-[A-Z0-9]{6,20}$/.test(measurementId)) return;

  const storageKey = "sm360-analytics-consent:" + measurementId;
  let consent = "unset";
  let analyticsLoaded = false;
  try { consent = localStorage.getItem(storageKey) || "unset"; } catch (_) {}

  const style = document.createElement("style");
  style.textContent =
    ".sm360-privacy-banner{position:fixed;z-index:100000;right:16px;bottom:16px;width:min(470px,calc(100vw - 32px));box-sizing:border-box;padding:18px;border:1px solid #d8ded8;border-radius:16px;background:#fff;color:#1e2b23;box-shadow:0 12px 40px #10251d2b;font:14px/1.9 Vazirmatn,Tahoma,sans-serif;direction:rtl}" +
    ".sm360-privacy-banner p{margin:0 0 12px}.sm360-privacy-actions{display:flex;gap:8px;flex-wrap:wrap}" +
    ".sm360-privacy-actions button,.sm360-privacy-settings{font:inherit;cursor:pointer;border-radius:10px;padding:8px 14px}" +
    ".sm360-privacy-accept{background:#173d2d;color:#fff;border:1px solid #173d2d}.sm360-privacy-reject{background:#fff;color:#24352a;border:1px solid #b9c5bb}" +
    ".sm360-privacy-settings{position:fixed;z-index:99999;bottom:12px;left:12px;background:#fff;color:#34463a;border:1px solid #cbd5cc;box-shadow:0 3px 12px #10251d22;font:11px/1.5 Vazirmatn,Tahoma,sans-serif}" +
    ".sm360-privacy-banner[hidden]{display:none}";
  document.head.appendChild(style);

  function setConsent(value) {
    consent = value;
    try { localStorage.setItem(storageKey, value); } catch (_) {}
    const banner = document.getElementById("sm360PrivacyBanner");
    if (banner) banner.hidden = true;
    if (value === "accepted") loadAnalytics();
    else if (analyticsLoaded && typeof window.gtag === "function") {
      window.gtag("consent", "update", {analytics_storage:"denied",ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied"});
    }
    updateTracker();
  }

  function updateTracker() {
    window.sm360Track = (eventName, parameters = {}) => {
      if (consent === "accepted" && analyticsLoaded && typeof window.gtag === "function") {
        window.gtag("event", eventName, parameters || {});
      }
    };
  }

  function loadAnalytics() {
    if (analyticsLoaded) return;
    analyticsLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function(){ window.dataLayer.push(arguments); };
    window.gtag("consent", "default", {analytics_storage:"denied",ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied"});
    window.gtag("consent", "update", {analytics_storage:"granted"});
    window.gtag("js", new Date());
    window.gtag("config", measurementId);
    const script = document.createElement("script");
    script.async = true;
    script.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(measurementId);
    document.head.appendChild(script);
    updateTracker();
    document.dispatchEvent(new Event("sm360:analytics-ready"));
  }

  const banner = document.createElement("section");
  banner.id = "sm360PrivacyBanner";
  banner.className = "sm360-privacy-banner";
  banner.setAttribute("role", "dialog");
  banner.setAttribute("aria-label", "تنظیمات حریم خصوصی و آمار بازدید");
  banner.hidden = true;
  const message = document.createElement("p");
  message.textContent = "برای تحلیل آمار بازدید و بهبود فروشگاه، فقط با رضایت شما از Google Analytics استفاده می‌کنیم. انتخاب شما برای این فروشگاه ذخیره می‌شود.";
  const actions = document.createElement("div");
  actions.className = "sm360-privacy-actions";
  const accept = document.createElement("button");
  accept.type = "button";
  accept.className = "sm360-privacy-accept";
  accept.textContent = "پذیرش آمار";
  accept.addEventListener("click", () => setConsent("accepted"));
  const reject = document.createElement("button");
  reject.type = "button";
  reject.className = "sm360-privacy-reject";
  reject.textContent = "رد کردن";
  reject.addEventListener("click", () => setConsent("rejected"));
  actions.append(accept, reject);
  banner.append(message, actions);
  document.body.appendChild(banner);

  const preferences = document.createElement("button");
  preferences.type = "button";
  preferences.className = "sm360-privacy-settings";
  preferences.textContent = "تنظیمات حریم خصوصی";
  preferences.addEventListener("click", () => { banner.hidden = !banner.hidden; });
  document.body.appendChild(preferences);

  updateTracker();
  if (consent === "accepted") loadAnalytics();
  else if (consent === "unset") banner.hidden = false;
})();
