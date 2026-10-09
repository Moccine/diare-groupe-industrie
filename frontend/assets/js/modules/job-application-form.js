const MAX_CV_BYTES = 5242880;

export function initJobApplicationForm() {
  document.querySelectorAll("[data-job-application]").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    initCounter(form);
    initCv(form);
  });
}

function initCounter(form) {
  const field = form.querySelector("[data-char-max]");
  const counter = form.querySelector("[data-char-count]");
  if (!(field instanceof HTMLTextAreaElement) || !(counter instanceof HTMLElement)) {
    return;
  }

  const max = Number(field.dataset.charMax || "2000");
  const render = () => {
    counter.textContent = `${field.value.length} / ${max}`;
  };

  field.addEventListener("input", render);
  render();
}

function initCv(form) {
  const zone = form.querySelector("[data-cv-drop]");
  const input = zone?.querySelector('input[type="file"]');
  const fileCard = zone?.querySelector("[data-cv-file]");
  const name = zone?.querySelector("[data-cv-name]");
  const size = zone?.querySelector("[data-cv-size]");
  const clear = zone?.querySelector("[data-cv-clear]");
  if (!(zone instanceof HTMLElement) || !(input instanceof HTMLInputElement) || !(fileCard instanceof HTMLElement)) {
    return;
  }

  const show = () => {
    const file = input.files?.[0];
    input.setCustomValidity("");
    if (!file) {
      fileCard.hidden = true;
      return;
    }

    if (file.size < 1) {
      input.setCustomValidity("Le fichier CV est vide.");
    } else if (file.size > MAX_CV_BYTES) {
      input.setCustomValidity("Le CV ne doit pas dépasser 5 Mo.");
    } else if (!file.name.toLowerCase().endsWith(".pdf")) {
      input.setCustomValidity("Le CV doit être un fichier PDF.");
    }

    if (name) {
      name.textContent = file.name;
    }
    if (size) {
      size.textContent = readableSize(file.size);
    }
    fileCard.hidden = false;
  };

  input.addEventListener("change", show);

  ["dragenter", "dragover"].forEach((eventName) => {
    zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.add("is-dragover");
    });
  });

  ["dragleave", "drop"].forEach((eventName) => {
    zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.remove("is-dragover");
    });
  });

  zone.addEventListener("drop", (event) => {
    const files = event.dataTransfer?.files;
    if (!files || files.length === 0) {
      return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(files[0]);
    input.files = transfer.files;
    input.dispatchEvent(new Event("change", { bubbles: true }));
  });

  clear?.addEventListener("click", () => {
    input.value = "";
    input.setCustomValidity("");
    fileCard.hidden = true;
  });
}

function readableSize(bytes) {
  if (bytes < 1024) {
    return `${bytes} o`;
  }
  if (bytes < 1048576) {
    return `${(bytes / 1024).toFixed(1).replace(".", ",")} Ko`;
  }

  return `${(bytes / 1048576).toFixed(1).replace(".", ",")} Mo`;
}
