export function initParallax() {
  const nodes = [...document.querySelectorAll("[data-parallax]")];
  if (!nodes.length || !("IntersectionObserver" in window)) {
    return;
  }

  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)");
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !finePointer.matches) {
    return;
  }

  let frame = 0;

  const update = () => {
    const height = window.innerHeight || 1;
    nodes.forEach((node) => {
      const rect = node.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > height) {
        return;
      }
      const amplitude = Math.min(30, Math.max(10, Number(node.dataset.parallax) || 16));
      const progress = (rect.top + rect.height / 2 - height / 2) / height;
      const shift = Math.max(-amplitude, Math.min(amplitude, progress * amplitude));
      node.style.transform = `translate3d(0, ${shift.toFixed(2)}px, 0)`;
    });
    frame = 0;
  };

  const requestUpdate = () => {
    if (frame) {
      return;
    }
    frame = window.requestAnimationFrame(update);
  };

  update();
  window.addEventListener("scroll", requestUpdate, { passive: true });
  window.addEventListener("resize", requestUpdate);
}
