import { refreshImageFallback } from "./image-fallback";

const sameCatalogUrl = (left, right) => {
  const current = new URL(left, window.location.href);
  const next = new URL(right, window.location.href);

  return current.origin === next.origin
    && current.pathname === next.pathname
    && current.search === next.search;
};

const shouldLeaveToBrowser = (event, link) => {
  if (event.defaultPrevented || event.button !== 0) {
    return true;
  }

  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return true;
  }

  return link.target !== "" && link.target !== "_self";
};

export function initCatalogFilter() {
  const root = document.querySelector("[data-catalog]");
  if (!(root instanceof HTMLElement)) {
    return;
  }

  const results = root.querySelector("[data-catalog-results]");
  const filters = root.querySelector("[data-catalog-filters]");
  if (!(results instanceof HTMLElement)) {
    return;
  }

  let controller = null;
  let requestId = 0;
  let enteringTimer = 0;

  const syncFilters = (url) => {
    if (!(filters instanceof HTMLElement)) {
      return;
    }

    filters.querySelectorAll("a[data-catalog-link]").forEach((node) => {
      if (!(node instanceof HTMLAnchorElement)) {
        return;
      }

      if (sameCatalogUrl(node.href, url)) {
        node.setAttribute("aria-current", "true");
      } else {
        node.removeAttribute("aria-current");
      }
    });
  };

  const fail = (url) => {
    if (sameCatalogUrl(window.location.href, url)) {
      window.location.reload();
      return;
    }

    window.location.assign(url);
  };

  const load = (url, push) => {
    if (controller) {
      controller.abort();
    }

    controller = new AbortController();
    const { signal } = controller;
    const id = ++requestId;

    results.classList.remove("is-entering");
    results.classList.add("is-loading");
    results.setAttribute("aria-busy", "true");

    fetch(url, {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "text/html",
      },
      signal,
      cache: "no-store",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("http");
        }

        return response.text();
      })
      .then((html) => {
        if (id !== requestId || signal.aborted) {
          return;
        }

        if (!html.includes("catalog-results__inner")) {
          throw new Error("html");
        }

        results.innerHTML = html;
        results.classList.remove("is-loading");
        results.classList.add("is-entering");
        results.setAttribute("aria-busy", "false");
        refreshImageFallback(results);
        syncFilters(url);

        window.clearTimeout(enteringTimer);
        enteringTimer = window.setTimeout(() => {
          results.classList.remove("is-entering");
        }, 360);

        if (push) {
          window.history.pushState({ catalog: true }, "", url);
        }
      })
      .catch((error) => {
        if (error instanceof DOMException && error.name === "AbortError") {
          return;
        }

        if (id !== requestId || signal.aborted) {
          return;
        }

        fail(url);
      });
  };

  const onCatalogLink = (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }

    const link = event.target.closest("a[data-catalog-link]");
    if (!(link instanceof HTMLAnchorElement) || !root.contains(link)) {
      return;
    }

    if (shouldLeaveToBrowser(event, link)) {
      return;
    }

    event.preventDefault();

    if (sameCatalogUrl(link.href, window.location.href)) {
      return;
    }

    load(link.href, true);
  };

  root.addEventListener("click", onCatalogLink);
  window.addEventListener("popstate", () => {
    if (!document.body.contains(root)) {
      return;
    }

    load(window.location.href, false);
  });
}
