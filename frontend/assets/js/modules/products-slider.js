import Swiper from "swiper";
import { A11y, Autoplay, Keyboard, Navigation } from "swiper/modules";
import "swiper/css";

const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function initProductsSlider() {
  document.querySelectorAll("[data-products-slider]").forEach((swiperEl) => {
    const slides = swiperEl.querySelectorAll(".swiper-slide");
    if (slides.length < 2) {
      return;
    }

    const root = swiperEl.closest(".products") || swiperEl;
    const reduced = reducedMotion();
    const delay = Number(swiperEl.dataset.productsAutoplay) || 5000;

    const swiper = new Swiper(swiperEl, {
      modules: [Navigation, Autoplay, A11y, Keyboard],
      speed: reduced ? 0 : 650,
      rewind: true,
      watchOverflow: true,
      autoplay: reduced
        ? false
        : {
            delay,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
          },
      slidesPerView: 1.22,
      spaceBetween: 12,
      navigation: {
        prevEl: root.querySelector("[data-products-prev]"),
        nextEl: root.querySelector("[data-products-next]"),
      },
      keyboard: {
        enabled: true,
        onlyInViewport: true,
      },
      a11y: {
        enabled: true,
        prevSlideMessage: "Produit précédent",
        nextSlideMessage: "Produit suivant",
      },
      breakpoints: {
        768: {
          slidesPerView: 2.15,
          spaceBetween: 16,
        },
        1024: {
          slidesPerView: 2.35,
          spaceBetween: 18,
        },
        1280: {
          slidesPerView: 3.2,
          spaceBetween: 20,
        },
      },
    });

    if (reduced || !swiper.autoplay) {
      return;
    }

    const syncAutoplay = () => {
      if (swiper.isLocked) {
        swiper.autoplay.stop();
        return;
      }
      if (!swiper.autoplay.running) {
        swiper.autoplay.start();
      }
    };

    swiper.on("lock", syncAutoplay);
    swiper.on("unlock", syncAutoplay);
    syncAutoplay();

    let resumeTimer = 0;
    const pauseForInteraction = () => {
      if (!swiper.autoplay) {
        return;
      }

      swiper.autoplay.pause();
      window.clearTimeout(resumeTimer);
      resumeTimer = window.setTimeout(() => {
        if (!swiper.destroyed && !swiper.isLocked) {
          swiper.autoplay.resume();
        }
      }, 7000);
    };

    swiper.on("touchStart", pauseForInteraction);
    root.querySelector(".products-slider__nav")?.addEventListener("pointerdown", pauseForInteraction);

    root.addEventListener("focusin", () => {
      swiper.autoplay.pause();
    });
    root.addEventListener("focusout", (event) => {
      if (event.relatedTarget instanceof Node && root.contains(event.relatedTarget)) {
        return;
      }
      swiper.autoplay.resume();
    });
  });
}
