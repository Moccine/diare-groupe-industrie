export function initCounters() {
  const nodes = document.querySelectorAll("[data-counter]");
  if (!nodes.length) {
    return;
  }

  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const paint = (node, value, parts) => {
    if (!parts) {
      node.textContent = node.dataset.counter;
      return;
    }

    const rendered = parts.decimals
      ? value.toFixed(1).replace(".", ",")
      : String(Math.round(value));
    node.textContent = `${parts.prefix}${rendered}${parts.suffix}`;
  };

  const parse = (raw) => {
    const match = String(raw).trim().match(/^(\D*)(\d+(?:[.,]\d+)?)(\D*)$/);
    if (!match) {
      return null;
    }

    return {
      prefix: match[1],
      suffix: match[3],
      target: Number(match[2].replace(",", ".")),
      decimals: match[2].includes(",") || match[2].includes("."),
    };
  };

  const animate = (node) => {
    const parts = parse(node.dataset.counter);
    if (!parts || !Number.isFinite(parts.target) || reduced) {
      node.textContent = node.dataset.counter;
      return;
    }

    const start = performance.now();
    const duration = 700;
    const tick = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - (1 - progress) ** 3;
      paint(node, parts.target * eased, parts);
      if (progress < 1) {
        requestAnimationFrame(tick);
      }
    };
    requestAnimationFrame(tick);
  };

  if (!("IntersectionObserver" in window)) {
    nodes.forEach(animate);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animate(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.45 });

  nodes.forEach((node) => observer.observe(node));
}
