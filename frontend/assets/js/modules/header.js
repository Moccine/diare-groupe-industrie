export function initHeader() {
  const header = document.querySelector("[data-header]");
  if (!header) {
    return;
  }

  const onScroll = () => {
    header.classList.toggle("is-scrolled", window.scrollY > 12);
  };

  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
}
