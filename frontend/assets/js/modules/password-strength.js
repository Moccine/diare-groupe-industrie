const RULES = [
    { id: 'length', label: 'Au moins 8 caractères', test: (value) => value.length >= 8 },
    { id: 'lower', label: 'Une minuscule', test: (value) => /[a-z]/.test(value) },
    { id: 'upper', label: 'Une majuscule', test: (value) => /[A-Z]/.test(value) },
    { id: 'digit', label: 'Un chiffre', test: (value) => /\d/.test(value) },
    { id: 'special', label: 'Un caractère spécial', test: (value) => /[@$!%*?&#\-_.+=]/.test(value) },
    { id: 'allowed', label: 'Uniquement des lettres, chiffres et caractères spéciaux autorisés', test: (value) => value === '' || /^[A-Za-z\d@$!%*?&#\-_.+=]+$/.test(value) },
];

export function initPasswordStrength() {
    document.querySelectorAll('[data-password-policy]').forEach((input) => {
        if (!(input instanceof HTMLInputElement) || input.dataset.passwordPolicyReady === '1') {
            return;
        }

        const form = input.form;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        input.dataset.passwordPolicyReady = '1';
        const confirmation = confirmationField(input);
        const meter = document.createElement('ul');
        meter.className = 'password-meter';
        meter.setAttribute('aria-live', 'polite');
        meter.setAttribute('aria-label', 'Robustesse du mot de passe');

        const items = new Map();
        for (const rule of [...RULES, { id: 'match', label: 'Les deux saisies correspondent', test: () => false }]) {
            const item = document.createElement('li');
            const state = document.createElement('span');
            state.className = 'password-meter__state';
            state.textContent = 'Manquant';
            item.append(state, document.createTextNode(` ${rule.label}`));
            meter.append(item);
            items.set(rule.id, { item, state });
        }

        meter.hidden = true;
        placeMeter(input, confirmation, meter);

        const reveal = () => {
            meter.hidden = false;
        };

        if (input.value !== '' || input.closest('.is-invalid') instanceof Element) {
            reveal();
        }

        input.addEventListener('focus', reveal);

        const paint = () => {
            const value = input.value;
            const optional = !input.required && value === '' && (confirmation?.value ?? '') === '';
            let valid = true;

            for (const rule of RULES) {
                const passed = rule.test(value);
                const met = value !== '' && passed;
                const counts = !optional && (rule.id === 'allowed' ? value !== '' : true);
                if (counts && !passed) {
                    valid = false;
                }
                paintItem(items.get(rule.id), met, optional || (rule.id === 'allowed' && value === ''));
            }

            const same = confirmation instanceof HTMLInputElement && value !== '' && value === confirmation.value;
            if (!optional && !same) {
                valid = false;
            }
            paintItem(items.get('match'), same, optional);

            if (optional) {
                input.removeAttribute('aria-invalid');
            }

            return optional || valid;
        };

        const reject = (event) => {
            if (paint()) {
                input.removeAttribute('aria-invalid');
                return;
            }

            event.preventDefault();
            input.setAttribute('aria-invalid', 'true');
            input.focus();
        };

        input.addEventListener('input', paint);
        confirmation?.addEventListener('input', paint);
        form.addEventListener('submit', reject, true);
        paint();
    });
}

function paintItem(entry, met, optional) {
    if (!entry) {
        return;
    }

    entry.item.classList.toggle('is-met', met);
    entry.item.classList.toggle('is-idle', optional);
    entry.state.textContent = optional ? 'À remplir' : (met ? 'Atteint' : 'Manquant');
}

function placeMeter(input, confirmation, meter) {
    const hint = input.form?.querySelector('.login__hint');
    if (hint instanceof HTMLElement) {
        hint.hidden = true;
    }

    const anchor = confirmation instanceof HTMLInputElement
        ? fieldBlock(confirmation)
        : fieldBlock(input);
    anchor.insertAdjacentElement('afterend', meter);
}

function fieldBlock(input) {
    return input.closest('.form-field, .form-group, .password-field') ?? input;
}

function confirmationField(input) {
    const name = input.getAttribute('name') || '';
    if (!name.endsWith('[first]') || !(input.form instanceof HTMLFormElement)) {
        return null;
    }

    const field = input.form.elements.namedItem(`${name.slice(0, -7)}[second]`);

    return field instanceof HTMLInputElement ? field : null;
}
