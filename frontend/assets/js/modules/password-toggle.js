export function setPasswordRevealed(input, revealed) {
  const button = input.parentElement?.querySelector("[data-password-toggle]");
  input.type = revealed ? "text" : "password";

  if (!(button instanceof HTMLButtonElement)) {
    return;
  }

  button.setAttribute("aria-pressed", revealed ? "true" : "false");
  button.setAttribute("aria-label", revealed ? "Masquer le mot de passe" : "Afficher le mot de passe");
  button.querySelector("[data-password-icon='show']")?.toggleAttribute("hidden", revealed);
  button.querySelector("[data-password-icon='hide']")?.toggleAttribute("hidden", !revealed);
}

export function initPasswordToggle() {
  document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    const inputId = button.getAttribute("aria-controls");
    const input = inputId ? document.getElementById(inputId) : null;

    if (!(input instanceof HTMLInputElement) || button.dataset.passwordToggleReady === "1") {
      return;
    }

    button.dataset.passwordToggleReady = "1";
    button.addEventListener("click", () => {
      setPasswordRevealed(input, input.type === "password");
    });
  });
}
