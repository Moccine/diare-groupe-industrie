const markBroken = (img) => {
  const slot = img.closest("[data-media-slot]");
  if (!(slot instanceof HTMLElement) || slot.classList.contains("is-broken")) {
    return;
  }

  slot.classList.add("is-broken");
  img.hidden = true;

  const fallback = slot.querySelector("[data-media-fallback]");
  if (!(fallback instanceof HTMLElement)) {
    return;
  }

  const placeholder = fallback.querySelector(".media-placeholder");
  if (placeholder instanceof HTMLElement && placeholder.classList.contains("media-placeholder--product")) {
    fallback.hidden = false;
    return;
  }

  const caption = fallback.querySelector(".media-placeholder__caption");
  if (img.alt.trim() !== "" && placeholder instanceof HTMLElement) {
    placeholder.removeAttribute("aria-hidden");
    if (caption instanceof HTMLElement && caption.textContent.trim() === "") {
      caption.textContent = img.alt;
    }
  }

  fallback.hidden = false;
};

const replaceLogo = (img) => {
  if (img.closest(".media-placeholder--product") instanceof HTMLElement) {
    img.hidden = true;
    return;
  }

  const mark = document.createElement("span");
  mark.className = img.classList.contains("site-loader__logo")
    ? "site-loader__word"
    : "media-placeholder__monogram";
  mark.textContent = "DGI";
  img.replaceWith(mark);
};

const inspectImage = (node) => {
  if (!(node instanceof HTMLImageElement) || !node.complete || node.naturalWidth !== 0) {
    return;
  }

  if (node.hasAttribute("data-logo-mark")) {
    replaceLogo(node);
    return;
  }

  markBroken(node);
};

export function refreshImageFallback(root = document) {
  root.querySelectorAll("[data-media-image], [data-logo-mark]").forEach(inspectImage);
};

export function initImageFallback() {
  document.addEventListener(
    "error",
    (event) => {
      const target = event.target;
      if (!(target instanceof HTMLImageElement)) {
        return;
      }
      if (target.hasAttribute("data-logo-mark")) {
        replaceLogo(target);
        return;
      }
      if (target.hasAttribute("data-media-image")) {
        markBroken(target);
      }
    },
    true,
  );

  refreshImageFallback(document);
}
