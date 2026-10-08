const COOKIE_NAME = "analytics_consent";
const MAX_AGE = 60 * 60 * 24 * 180;

function writeConsent(value) {
  const secure = window.location.protocol === "https:" ? "; Secure" : "";
  document.cookie = `${COOKIE_NAME}=${value}; Path=/; Max-Age=${MAX_AGE}; SameSite=Lax${secure}`;
  window.location.reload();
}

function clearConsent() {
  document.cookie = `${COOKIE_NAME}=; Path=/; Max-Age=0; SameSite=Lax`;
  window.location.reload();
}

export function initAnalyticsConsent() {
  const banner = document.querySelector("[data-analytics-banner]");
  banner?.querySelector("[data-analytics-accept]")?.addEventListener("click", () => {
    writeConsent("accepted");
  });
  banner?.querySelector("[data-analytics-refuse]")?.addEventListener("click", () => {
    writeConsent("refused");
  });

  document.querySelectorAll("[data-analytics-consent-reset]").forEach((button) => {
    button.addEventListener("click", () => {
      clearConsent();
    });
  });
}
