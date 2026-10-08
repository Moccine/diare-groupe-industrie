export function initPasswordToggle() {
  document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    const inputId = button.getAttribute("aria-controls");
    const input = inputId ? document.getElementById(inputId) : null;

    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    button.addEventListener("click", () => {
      const revealed = input.type === "password";
      input.type = revealed ? "text" : "password";
      button.setAttribute("aria-pressed", revealed ? "true" : "false");
      button.setAttribute("aria-label", revealed ? "Masquer le mot de passe" : "Afficher le mot de passe");
      button.querySelector("[data-password-icon='show']")?.toggleAttribute("hidden", revealed);
      button.querySelector("[data-password-icon='hide']")?.toggleAttribute("hidden", !revealed);
    });
  });
}
