export function initFormSubmitLock() {
  document.querySelectorAll("[data-form-submit-lock]").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.addEventListener("submit", (event) => {
      if (event.defaultPrevented || !form.checkValidity()) {
        return;
      }

      if (form.dataset.recaptchaSiteKey && form.dataset.recaptchaReady !== "1") {
        return;
      }

      if (form.classList.contains("is-submitting")) {
        event.preventDefault();
        return;
      }

      form.classList.add("is-submitting");
      const button = form.querySelector('[type="submit"]');
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }

      button.disabled = true;
      button.setAttribute("aria-disabled", "true");
      const spinner = document.createElement("span");
      spinner.className = "button-spinner";
      spinner.setAttribute("aria-hidden", "true");
      const text = document.createElement("span");
      text.textContent = "Envoi en cours…";
      button.replaceChildren(spinner, text);
    });
  });
}
