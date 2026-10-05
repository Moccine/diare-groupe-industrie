const reduceMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function initLoader() {
  const loader = document.querySelector("[data-site-loader]");
  if (!(loader instanceof HTMLElement)) {
    return;
  }

  let finished = false;

  const finish = () => {
    if (finished) {
      return;
    }
    finished = true;
    loader.classList.add("is-done");
    loader.hidden = true;
  };

  const hide = () => {
    if (finished || loader.classList.contains("is-leaving")) {
      return;
    }

    if (reduceMotion()) {
      finish();
      return;
    }

    loader.classList.add("is-leaving");
    const onEnd = (event) => {
      if (event.target !== loader || event.propertyName !== "opacity") {
        return;
      }
      loader.removeEventListener("transitionend", onEnd);
      finish();
    };
    loader.addEventListener("transitionend", onEnd);
    window.setTimeout(finish, 450);
  };

  if (document.readyState === "complete") {
    hide();
  } else {
    window.addEventListener("load", hide, { once: true });
  }

  window.addEventListener("pageshow", (event) => {
    if (!event.persisted) {
      return;
    }
    finished = false;
    finish();
  });
}
