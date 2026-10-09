export function initRecaptchaForms() {
  document.querySelectorAll("form[data-recaptcha-site-key]").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const siteKey = form.dataset.recaptchaSiteKey || "";
    const input = form.querySelector("[data-recaptcha-token]");
    if (siteKey === "" || !(input instanceof HTMLInputElement)) {
      return;
    }

    form.addEventListener("submit", (event) => {
      if (event.defaultPrevented || !form.checkValidity()) {
        return;
      }

      if (form.dataset.recaptchaReady === "1") {
        return;
      }

      if (form.dataset.recaptchaPending === "1") {
        event.preventDefault();
        return;
      }

      const grecaptcha = window.grecaptcha;
      if (!grecaptcha || typeof grecaptcha.execute !== "function") {
        return;
      }

      const action = form.dataset.recaptchaAction || "contact";
      event.preventDefault();
      form.dataset.recaptchaPending = "1";
      grecaptcha.ready(() => {
        grecaptcha.execute(siteKey, { action }).then((token) => {
          input.value = token;
          form.dataset.recaptchaPending = "0";
          form.dataset.recaptchaReady = "1";
          form.requestSubmit();
        }).catch(() => {
          form.dataset.recaptchaPending = "0";
          form.dataset.recaptchaReady = "1";
          form.requestSubmit();
        });
      });
    });
  });
}
