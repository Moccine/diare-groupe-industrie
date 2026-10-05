const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function initReveal() {
  if (!("IntersectionObserver" in window) || reducedMotion()) {
    return;
  }

  document.documentElement.classList.add("js-reveal");
  const nodes = document.querySelectorAll("[data-reveal]");
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.16, rootMargin: "0px 0px -8% 0px" });

  nodes.forEach((node) => observer.observe(node));
}
