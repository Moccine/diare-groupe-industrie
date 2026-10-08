import "../scss/site.scss";
import { initLoader } from "./modules/loader";
import { initImageFallback } from "./modules/image-fallback";
import { initHeader } from "./modules/header";
import { initMenu } from "./modules/menu";
import { initReveal } from "./modules/reveal";
import { initCounters } from "./modules/counter";
import { initParallax } from "./modules/parallax";
import { initHeroSlider } from "./modules/hero-slider";
import { initProductsSlider } from "./modules/products-slider";
import { initCatalogFilter } from "./modules/catalog-filter";
import { initFormValidation } from "./modules/form-validation";
import { initFormSubmitLock } from "./modules/form-submit-lock";
import { initRecaptchaForms } from "./modules/recaptcha-form";
import { initAnalyticsConsent } from "./modules/analytics-consent";
import { initPasswordToggle } from "./modules/password-toggle";
import { initPasswordStrength } from "./modules/password-strength";

initLoader();
initImageFallback();

document.addEventListener("DOMContentLoaded", () => {
  initHeader();
  initMenu();
  initReveal();
  initCounters();
  initParallax();
  initHeroSlider();
  initProductsSlider();
  initCatalogFilter();
  initFormValidation();
  initRecaptchaForms();
  initFormSubmitLock();
  initAnalyticsConsent();
  initPasswordToggle();
  initPasswordStrength();
});
