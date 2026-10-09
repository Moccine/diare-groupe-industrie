export function initFormValidation() {
  document.querySelectorAll("[data-validate]").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const fields = () => [...form.querySelectorAll("input, textarea, select")].filter((field) => {
      return (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)
        && !field.disabled
        && field.type !== "hidden"
        && field.type !== "submit"
        && !field.classList.contains("hp");
    });

    fields().forEach((field) => {
      field.addEventListener("blur", () => {
        field.dataset.touched = "true";
        validateField(field);
      });

      field.addEventListener("input", () => {
        if (field.dataset.touched === "true" || field.getAttribute("aria-invalid") === "true") {
          validateField(field);
        }
      });

      field.addEventListener("change", () => {
        if (field.type === "checkbox") {
          field.dataset.touched = "true";
        }
        if (field.dataset.touched === "true") {
          validateField(field);
        }
      });
    });

    form.addEventListener("submit", (event) => {
      const invalid = [];
      fields().forEach((field) => {
        field.dataset.touched = "true";
        if (!validateField(field)) {
          invalid.push(field);
        }
      });

      if (invalid.length === 0) {
        return;
      }

      event.preventDefault();
      const first = invalid[0];
      const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      first.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "center" });
      first.focus({ preventScroll: true });
    }, true);
  });
}

function validateField(field) {
  if (field.checkValidity()) {
    clearError(field);
    return true;
  }

  showError(field);
  return false;
}

function showError(field) {
  const node = errorNode(field);
  node.dataset.clientError = "true";
  node.textContent = messageFor(field);
  field.setAttribute("aria-invalid", "true");
  const described = new Set((field.getAttribute("aria-describedby") || "").split(" ").filter(Boolean));
  described.add(node.id);
  field.setAttribute("aria-describedby", [...described].join(" "));
  field.closest(".form-field, .consent")?.classList.add("is-invalid");
}

function clearError(field) {
  field.removeAttribute("aria-invalid");
  const errorId = `${field.id}-error`;
  const described = (field.getAttribute("aria-describedby") || "")
    .split(" ")
    .filter((token) => token && token !== errorId);
  if (described.length > 0) {
    field.setAttribute("aria-describedby", described.join(" "));
  } else {
    field.removeAttribute("aria-describedby");
  }
  document.getElementById(errorId)?.remove();
  field.closest(".form-field, .consent")?.classList.remove("is-invalid");
}

function errorNode(field) {
  const id = `${field.id}-error`;
  const existing = document.getElementById(id);
  if (existing) {
    return existing;
  }

  const node = document.createElement("p");
  node.className = "field-error";
  node.id = id;
  node.setAttribute("role", "alert");
  field.insertAdjacentElement("afterend", node);
  return node;
}

function messageFor(field) {
  const validity = field.validity;
  if (validity.customError && field.validationMessage) {
    return field.validationMessage;
  }
  if (validity.valueMissing) {
    return field.type === "checkbox"
      ? "Le consentement est obligatoire."
      : "Ce champ est obligatoire.";
  }
  if (validity.typeMismatch) {
    return "Indiquez une adresse e-mail valide.";
  }
  if (validity.tooShort) {
    return `Saisissez au moins ${field.minLength} caractères.`;
  }
  if (validity.tooLong) {
    return "Ce champ est trop long.";
  }
  if (validity.patternMismatch) {
    return "Le format saisi n’est pas valide.";
  }

  return "Vérifiez ce champ.";
}
