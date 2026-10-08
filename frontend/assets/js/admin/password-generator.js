import { setPasswordRevealed } from '../modules/password-toggle';

const LOWER = 'abcdefghijkmnopqrstuvwxyz';
const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
const DIGITS = '23456789';
const SPECIAL = '@$!%*?&#-_.+=';
const ALL = `${LOWER}${UPPER}${DIGITS}${SPECIAL}`;
const LENGTH = 20;

function randomIndex(size) {
    const values = new Uint32Array(1);
    const limit = Math.floor(0x100000000 / size) * size;
    let value = 0;

    do {
        crypto.getRandomValues(values);
        value = values[0];
    } while (value >= limit);

    return value % size;
}

function pick(alphabet) {
    return alphabet[randomIndex(alphabet.length)];
}

function generatePassword() {
    const chars = [pick(LOWER), pick(UPPER), pick(DIGITS), pick(SPECIAL)];

    while (chars.length < LENGTH) {
        chars.push(pick(ALL));
    }

    for (let index = chars.length - 1; index > 0; index -= 1) {
        const swap = randomIndex(index + 1);
        const current = chars[index];
        chars[index] = chars[swap];
        chars[swap] = current;
    }

    return chars.join('');
}

function setStatus(status, message) {
    status.textContent = message;
}

function enhance(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.dgiPasswordReady === '1') {
        return;
    }

    input.dataset.dgiPasswordReady = '1';

    const tools = document.createElement('div');
    tools.className = 'dgi-password-tools';

    const generate = document.createElement('button');
    generate.type = 'button';
    generate.className = 'btn btn-secondary';
    generate.textContent = 'Générer';

    const copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'btn btn-secondary';
    copy.textContent = 'Copier';
    copy.disabled = true;

    const status = document.createElement('p');
    status.className = 'dgi-password-tools__status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    generate.addEventListener('click', () => {
        const password = generatePassword();
        fill(input, password);
        const confirmation = confirmationField(input);
        if (confirmation instanceof HTMLInputElement) {
            fill(confirmation, password);
        }
        setPasswordRevealed(input, true);
        if (confirmation instanceof HTMLInputElement) {
            setPasswordRevealed(confirmation, true);
        }
        input.focus();
        copy.disabled = false;
        setStatus(status, 'Mot de passe généré et recopié dans la confirmation. L’œil le masque à nouveau.');
    });

    copy.addEventListener('click', async () => {
        const password = input.value;
        if (password === '') {
            setStatus(status, 'Générez d’abord un mot de passe.');
            return;
        }

        try {
            await navigator.clipboard.writeText(password);
            setStatus(status, 'Mot de passe copié.');
        } catch {
            input.focus();
            input.select();
            setStatus(status, 'Sélectionné. Copiez-le avec le raccourci du clavier.');
        }
    });

    tools.append(generate, copy, status);
    const anchor = input.closest('.password-field') ?? input;
    anchor.insertAdjacentElement('afterend', tools);
}

function confirmationField(input) {
    const name = input.getAttribute('name') || '';
    const confirmationName = name.endsWith('[first]') ? `${name.slice(0, -7)}[second]` : '';
    if (confirmationName === '' || !(input.form instanceof HTMLFormElement)) {
        return null;
    }

    const field = input.form.elements.namedItem(confirmationName);

    return field instanceof HTMLInputElement ? field : null;
}

function fill(input, password) {
    input.value = password;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

document.querySelectorAll('[data-dgi-password-generator]').forEach(enhance);
