const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function initPresentationVideo() {
  const videos = document.querySelectorAll("[data-presentation-video]");
  if (!videos.length) {
    return;
  }

  const motion = window.matchMedia("(prefers-reduced-motion: reduce)");

  const sync = (video) => {
    if (reducedMotion()) {
      video.autoplay = false;
      video.pause();
      return;
    }

    video.muted = true;
    const play = video.play();
    if (play && typeof play.catch === "function") {
      play.catch(() => {
        video.classList.add("is-unavailable");
      });
    }
  };

  videos.forEach((video) => {
    video.addEventListener("error", () => {
      video.classList.add("is-unavailable");
    });
    sync(video);
  });

  motion.addEventListener("change", () => {
    videos.forEach((video) => {
      video.classList.remove("is-unavailable");
      sync(video);
    });
  });
}
