import Swal from "sweetalert2";
import "sweetalert2/dist/sweetalert2.css";
import "../../scss/components/product-lightbox.scss";

const bound = new WeakSet();

const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const readItem = (button, fallback) => ({
  button,
  src: button?.getAttribute("data-src") || fallback?.getAttribute("src") || "",
  alt: button?.getAttribute("data-alt") || fallback?.getAttribute("alt") || "",
  width: button?.getAttribute("data-width") || fallback?.getAttribute("width") || "",
  height: button?.getAttribute("data-height") || fallback?.getAttribute("height") || "",
});

const svgArrow = (direction) => {
  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("aria-hidden", "true");
  svg.setAttribute("focusable", "false");
  const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
  path.setAttribute("d", direction === "prev" ? "M14.5 5.5 8 12l6.5 6.5" : "M9.5 5.5 16 12l-6.5 6.5");
  path.setAttribute("fill", "none");
  path.setAttribute("stroke", "currentColor");
  path.setAttribute("stroke-width", "1.8");
  path.setAttribute("stroke-linecap", "round");
  path.setAttribute("stroke-linejoin", "round");
  svg.append(path);

  return svg;
};

const bindGallery = (gallery) => {
  const openButton = gallery.querySelector("[data-product-gallery-open]");
  const main = gallery.querySelector("[data-product-gallery-main]");
  if (!(openButton instanceof HTMLButtonElement) || !(main instanceof HTMLImageElement)) {
    return;
  }

  const slot = main.closest("[data-media-slot]");
  const fallback = slot instanceof HTMLElement ? slot.querySelector("[data-media-fallback]") : null;
  const thumbs = gallery.querySelector("[data-product-gallery-thumbs]");
  const rail = gallery.querySelector("[data-product-gallery-rail]");
  const thumbButtons = thumbs instanceof HTMLElement
    ? [...thumbs.querySelectorAll("[data-product-gallery-thumb]")]
    : [];
  const items = thumbButtons.length > 0
    ? thumbButtons.map((button) => readItem(button instanceof HTMLButtonElement ? button : null, main))
    : [readItem(null, main)];

  if (items.every((item) => item.src === "")) {
    return;
  }

  let index = Math.max(0, items.findIndex((item) => item.button instanceof HTMLButtonElement && item.button.classList.contains("is-active")));
  let fadeTimer = 0;
  let lightbox = null;

  const broken = () => slot instanceof HTMLElement && slot.classList.contains("is-broken");

  const resetSlot = () => {
    if (slot instanceof HTMLElement) {
      slot.classList.remove("is-broken");
    }
    main.hidden = false;
    if (fallback instanceof HTMLElement) {
      fallback.hidden = true;
    }
  };

  const paintMain = (item) => {
    window.clearTimeout(fadeTimer);
    const apply = () => {
      if (main.getAttribute("src") !== item.src) {
        resetSlot();
      }
      main.src = item.src;
      main.alt = item.alt;
      if (item.width) {
        main.setAttribute("width", item.width);
      } else {
        main.removeAttribute("width");
      }
      if (item.height) {
        main.setAttribute("height", item.height);
      } else {
        main.removeAttribute("height");
      }
      openButton.setAttribute("aria-label", item.alt !== "" ? `Agrandir la photo : ${item.alt}` : "Agrandir la photo");
      main.classList.remove("is-fading");
      openButton.classList.remove("is-fading");
    };

    if (reducedMotion() || main.getAttribute("src") === item.src) {
      apply();
      return;
    }

    openButton.classList.add("is-fading");
    fadeTimer = window.setTimeout(apply, 140);
  };

  const revealThumb = (button) => {
    if (!(thumbs instanceof HTMLElement) || !(button instanceof HTMLButtonElement)) {
      return;
    }

    const vertical = getComputedStyle(thumbs).flexDirection.startsWith("column");
    const behavior = reducedMotion() ? "auto" : "smooth";
    if (vertical) {
      const top = button.offsetTop;
      const bottom = top + button.offsetHeight;
      if (top < thumbs.scrollTop) {
        thumbs.scrollTo({ top, behavior });
      } else if (bottom > thumbs.scrollTop + thumbs.clientHeight) {
        thumbs.scrollTo({ top: bottom - thumbs.clientHeight, behavior });
      }
      return;
    }

    const left = button.offsetLeft;
    const right = left + button.offsetWidth;
    if (left < thumbs.scrollLeft) {
      thumbs.scrollTo({ left, behavior });
    } else if (right > thumbs.scrollLeft + thumbs.clientWidth) {
      thumbs.scrollTo({ left: right - thumbs.clientWidth, behavior });
    }
  };

  const markActive = (next) => {
    items.forEach((item, itemIndex) => {
      if (!(item.button instanceof HTMLButtonElement)) {
        return;
      }

      const active = itemIndex === next;
      item.button.classList.toggle("is-active", active);
      item.button.setAttribute("aria-pressed", active ? "true" : "false");
      if (active) {
        item.button.setAttribute("aria-current", "true");
        revealThumb(item.button);
      } else {
        item.button.removeAttribute("aria-current");
      }
    });
  };

  const select = (next) => {
    if (items.length === 0) {
      return;
    }

    index = ((next % items.length) + items.length) % items.length;
    paintMain(items[index]);
    markActive(index);
  };

  const updateScrollControls = () => {
    if (!(thumbs instanceof HTMLElement) || !(rail instanceof HTMLElement)) {
      return;
    }

    const vertical = getComputedStyle(thumbs).flexDirection.startsWith("column");
    const max = vertical ? thumbs.scrollHeight - thumbs.clientHeight : thumbs.scrollWidth - thumbs.clientWidth;
    const pos = vertical ? thumbs.scrollTop : thumbs.scrollLeft;
    const overflow = max > 4;
    rail.classList.toggle("is-scrollable", overflow);
    rail.querySelectorAll("[data-product-gallery-scroll]").forEach((node) => {
      if (!(node instanceof HTMLButtonElement)) {
        return;
      }

      const direction = Number(node.getAttribute("data-product-gallery-scroll"));
      if (!overflow) {
        node.hidden = true;
        return;
      }

      node.hidden = direction < 0 ? pos <= 4 : pos >= max - 4;
    });
  };

  thumbButtons.forEach((node, itemIndex) => {
    if (!(node instanceof HTMLButtonElement)) {
      return;
    }

    node.addEventListener("click", () => {
      select(itemIndex);
      updateScrollControls();
    });

    const image = node.querySelector("img");
    if (image instanceof HTMLImageElement) {
      image.addEventListener("error", () => {
        image.hidden = true;
        node.classList.add("is-missing");
      });
    }
  });

  if (thumbs instanceof HTMLElement && rail instanceof HTMLElement) {
    thumbs.addEventListener("scroll", updateScrollControls, { passive: true });
    window.addEventListener("resize", updateScrollControls);
    rail.querySelectorAll("[data-product-gallery-scroll]").forEach((node) => {
      if (!(node instanceof HTMLButtonElement)) {
        return;
      }

      node.addEventListener("click", () => {
        const direction = Number(node.getAttribute("data-product-gallery-scroll")) || 0;
        const vertical = getComputedStyle(thumbs).flexDirection.startsWith("column");
        const distance = (vertical ? thumbs.clientHeight : thumbs.clientWidth) * 0.8 * direction;
        thumbs.scrollBy({
          top: vertical ? distance : 0,
          left: vertical ? 0 : distance,
          behavior: reducedMotion() ? "auto" : "smooth",
        });
      });
    });
    window.requestAnimationFrame(updateScrollControls);
  }

  const openLightbox = () => {
    if (broken() || main.getAttribute("src") === "" || Swal.isVisible()) {
      return;
    }

    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    let detach = () => {};

    Swal.fire({
      html: '<div class="dgi-product-lightbox__layout" data-lightbox-root></div>',
      showConfirmButton: false,
      showCloseButton: true,
      closeButtonAriaLabel: "Fermer",
      focusConfirm: false,
      returnFocus: false,
      heightAuto: false,
      scrollbarPadding: false,
      background: "transparent",
      width: "min(76rem, calc(100vw - 1.5rem))",
      padding: "0",
      backdrop: "rgba(11, 36, 20, 0.88)",
      customClass: {
        container: "dgi-product-lightbox",
        popup: "dgi-product-lightbox__popup",
        closeButton: "dgi-product-lightbox__close",
        htmlContainer: "dgi-product-lightbox__body",
      },
      didOpen: (popup) => {
        const root = popup.querySelector("[data-lightbox-root]");
        if (!(root instanceof HTMLElement)) {
          return;
        }

        root.classList.toggle("is-single", items.length < 2);

        const stage = document.createElement("div");
        stage.className = "dgi-product-lightbox__stage";

        const image = document.createElement("img");
        image.className = "dgi-product-lightbox__image";
        image.alt = "";
        image.decoding = "async";

        const missing = document.createElement("p");
        missing.className = "dgi-product-lightbox__missing";
        missing.textContent = "Cette photo est indisponible.";
        missing.hidden = true;

        const count = document.createElement("p");
        count.className = "dgi-product-lightbox__count";
        count.setAttribute("aria-live", "polite");

        stage.append(image, missing);
        root.append(stage);

        const show = (next) => {
          index = ((next % items.length) + items.length) % items.length;
          const item = items[index];
          missing.hidden = true;
          image.hidden = false;
          image.alt = item.alt;
          image.src = item.src;
          count.textContent = items.length > 1 ? `${index + 1} / ${items.length}` : "";
        };

        image.addEventListener("error", () => {
          image.hidden = true;
          image.alt = "";
          missing.hidden = false;
        });

        if (items.length > 1) {
          const previous = document.createElement("button");
          previous.type = "button";
          previous.className = "dgi-product-lightbox__nav dgi-product-lightbox__nav--prev";
          previous.setAttribute("aria-label", "Photo précédente");
          previous.append(svgArrow("prev"));
          previous.addEventListener("click", () => show(index - 1));

          const next = document.createElement("button");
          next.type = "button";
          next.className = "dgi-product-lightbox__nav dgi-product-lightbox__nav--next";
          next.setAttribute("aria-label", "Photo suivante");
          next.append(svgArrow("next"));
          next.addEventListener("click", () => show(index + 1));

          root.prepend(previous);
          root.append(next);
          root.append(count);

          let originX = null;
          let originY = null;
          let pointerId = null;

          stage.addEventListener("pointerdown", (event) => {
            if (event.pointerType === "mouse") {
              return;
            }
            originX = event.clientX;
            originY = event.clientY;
            pointerId = event.pointerId;
          });

          stage.addEventListener("pointerup", (event) => {
            if (originX === null || event.pointerId !== pointerId) {
              return;
            }
            const deltaX = event.clientX - originX;
            const deltaY = event.clientY - originY;
            originX = null;
            pointerId = null;
            if (Math.abs(deltaX) < 48 || Math.abs(deltaX) <= Math.abs(deltaY)) {
              return;
            }
            show(index + (deltaX < 0 ? 1 : -1));
          });

          stage.addEventListener("pointercancel", () => {
            originX = null;
            pointerId = null;
          });

          const onKey = (event) => {
            if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") {
              return;
            }
            event.preventDefault();
            show(index + (event.key === "ArrowRight" ? 1 : -1));
          };
          popup.addEventListener("keydown", onKey);
          detach = () => popup.removeEventListener("keydown", onKey);
        }

        lightbox = { show };
        show(index);
      },
      willClose: () => {
        detach();
        detach = () => {};
      },
      didClose: () => {
        lightbox = null;
        select(index);
        updateScrollControls();
        window.scrollTo(scrollX, scrollY);
        if (openButton.isConnected) {
          openButton.focus({ preventScroll: true });
        }
      },
    });
  };

  openButton.addEventListener("click", openLightbox);
};

export function initProductGalleries(root = document) {
  root.querySelectorAll("[data-product-gallery]").forEach((gallery) => {
    if (!(gallery instanceof HTMLElement) || bound.has(gallery)) {
      return;
    }

    bound.add(gallery);
    bindGallery(gallery);
  });
}
