const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const boundVideos = new WeakSet();
let motionBound = false;

const soundButton = (video) => video.closest(".presentation-video__frame")?.querySelector("[data-presentation-sound]") ?? null;

const paintSound = (video) => {
  const button = soundButton(video);
  if (!button) {
    return;
  }

  const muted = video.muted;
  button.classList.toggle("is-muted", muted);
  button.classList.toggle("is-audible", !muted);
  button.setAttribute("aria-pressed", muted ? "false" : "true");
  button.setAttribute("aria-label", muted ? "Activer le son" : "Couper le son");
};

const syncPlayback = (video) => {
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

const bindVideo = (video) => {
  if (boundVideos.has(video)) {
    return;
  }
  boundVideos.add(video);

  video.addEventListener("error", () => {
    video.classList.add("is-unavailable");
  });
  video.addEventListener("volumechange", () => {
    paintSound(video);
  });

  const button = soundButton(video);
  if (button) {
    button.addEventListener("click", () => {
      video.muted = !video.muted;
      if (!video.muted && video.volume === 0) {
        video.volume = 1;
      }
      paintSound(video);
    });
  }

  syncPlayback(video);
  paintSound(video);
};

export function initPresentationVideo() {
  const videos = document.querySelectorAll("[data-presentation-video]");
  if (!videos.length) {
    return;
  }

  videos.forEach(bindVideo);

  if (motionBound) {
    return;
  }
  motionBound = true;

  window.matchMedia("(prefers-reduced-motion: reduce)").addEventListener("change", () => {
    document.querySelectorAll("[data-presentation-video]").forEach((video) => {
      video.classList.remove("is-unavailable");
      syncPlayback(video);
      paintSound(video);
    });
  });
}
