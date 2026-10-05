import Swiper from "swiper";
import { A11y, Autoplay, EffectFade, Keyboard, Navigation, Pagination } from "swiper/modules";
import "swiper/css";
import "swiper/css/effect-fade";
import "swiper/css/pagination";

const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function initHeroSlider() {
  const root = document.querySelector("[data-hero-slider]");
  if (!root || root.classList.contains("hero-slider--single")) {
    return;
  }

  const swiperEl = root.querySelector(".hero-slider__swiper");
  if (!swiperEl) {
    return;
  }

  const reduced = reducedMotion();
  const delay = Number(root.dataset.heroAutoplay) || 6000;

  const swiper = new Swiper(swiperEl, {
    modules: [Navigation, Pagination, Autoplay, EffectFade, A11y, Keyboard],
    effect: "fade",
    fadeEffect: { crossFade: true },
    speed: reduced ? 0 : 850,
    rewind: true,
    autoplay: reduced
      ? false
      : {
          delay,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        },
    pagination: {
      el: root.querySelector(".hero-slider__pagination"),
      clickable: true,
    },
    navigation: {
      prevEl: root.querySelector("[data-hero-prev]"),
      nextEl: root.querySelector("[data-hero-next]"),
    },
    keyboard: {
      enabled: true,
      onlyInViewport: true,
    },
    a11y: {
      enabled: true,
      prevSlideMessage: "Diapositive précédente",
      nextSlideMessage: "Diapositive suivante",
      paginationBulletMessage: "Aller à la diapositive {{index}}",
    },
  });

  const settle = () => {
    swiper.slides.forEach((slide) => {
      slide.querySelectorAll(".hero-slide__copy > *").forEach((node) => {
        node.classList.remove("is-settled");
      });
    });

    const active = swiper.slides[swiper.activeIndex];
    if (!active) {
      return;
    }

    window.setTimeout(() => {
      if (!active.classList.contains("swiper-slide-active")) {
        return;
      }

      active.querySelectorAll(".hero-slide__copy > *").forEach((node) => {
        node.classList.add("is-settled");
      });
    }, reduced ? 0 : 980);
  };

  swiper.on("slideChangeTransitionStart", settle);

  let resumeTimer = 0;
  const pauseForInteraction = () => {
    if (reduced || !swiper.autoplay) {
      return;
    }

    swiper.autoplay.pause();
    window.clearTimeout(resumeTimer);
    resumeTimer = window.setTimeout(() => {
      if (!swiper.destroyed) {
        swiper.autoplay.resume();
      }
    }, 7000);
  };

  swiper.on("touchStart", pauseForInteraction);
  root.querySelector(".hero-slider__controls")?.addEventListener("pointerdown", pauseForInteraction);
  settle();
}
