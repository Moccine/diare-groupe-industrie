export function initMenu() {
  const toggle = document.querySelector("[data-nav-toggle]");
  const nav = document.querySelector("[data-nav]");
  if (!toggle || !nav) {
    return;
  }

  const label = toggle.querySelector(".visually-hidden");
  const background = [document.querySelector("main"), document.querySelector(".site-footer")];

  const setBackgroundInert = (inert) => {
    background.forEach((node) => {
      if (!node) {
        return;
      }
      if (inert) {
        node.setAttribute("inert", "");
      } else {
        node.removeAttribute("inert");
      }
    });
  };

  const close = () => {
    toggle.setAttribute("aria-expanded", "false");
    nav.classList.remove("is-open");
    document.body.classList.remove("nav-open");
    setBackgroundInert(false);
    if (label) {
      label.textContent = "Ouvrir le menu";
    }
  };

  const open = () => {
    toggle.setAttribute("aria-expanded", "true");
    nav.classList.add("is-open");
    document.body.classList.add("nav-open");
    setBackgroundInert(true);
    if (label) {
      label.textContent = "Fermer le menu";
    }
    const firstLink = nav.querySelector("a");
    if (firstLink) {
      firstLink.focus();
    }
  };

  toggle.addEventListener("click", () => {
    if (toggle.getAttribute("aria-expanded") === "true") {
      close();
      toggle.focus();
      return;
    }
    open();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
      close();
      toggle.focus();
    }
  });

  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.matchMedia("(max-width: 1023px)").matches) {
        close();
      }
    });
  });

  window.addEventListener("resize", () => {
    if (window.matchMedia("(min-width: 1024px)").matches && toggle.getAttribute("aria-expanded") === "true") {
      close();
    }
  });
}
