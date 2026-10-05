const objectUrls = new WeakMap();

function snapshot(select) {
    const map = new Map();
    for (const option of select.options) {
        if (!option.value) {
            continue;
        }
        map.set(option.value, {
            thumb: option.getAttribute('data-thumbnail') || '',
            label: option.getAttribute('data-label') || option.textContent.trim(),
        });
    }

    return map;
}

function paint(item, escape, map) {
    const html = typeof item.entityAsString === 'string' ? item.entityAsString : '';
    if (html.includes('dgi-media-option')) {
        return `<div>${html}</div>`;
    }

    const id = String(item.entityId ?? item.value ?? '');
    const known = map.get(id);
    const thumb = known?.thumb || item.$option?.dataset?.thumbnail || '';
    const label = escape(known?.label || item.text || '');
    const image = thumb
        ? `<img src="${escape(thumb)}" alt="" width="36" height="28" loading="lazy">`
        : '';

    return `<div class="dgi-media-option">${image}<span>${label}</span></div>`;
}

function formatWeight(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) {
        return '';
    }

    if (bytes < 1024) {
        return `${Math.round(bytes)} o`;
    }

    const useMega = bytes >= 1048576;
    const value = bytes / (useMega ? 1048576 : 1024);
    const rounded = Math.round(value * 10) / 10;
    const text = Number.isInteger(rounded)
        ? String(rounded)
        : rounded.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    return `${text} ${useMega ? 'Mo' : 'Ko'}`;
}

function button(label, className, action) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = className;
    element.dataset.dgiFileAction = action;
    element.textContent = label;

    return element;
}

function metaSpan(text) {
    const span = document.createElement('span');
    span.textContent = text;

    return span;
}

function createPreview(kind) {
    const preview = document.createElement('div');
    preview.className = `dgi-file-preview dgi-file-preview--${kind}`;
    preview.dataset.dgiFilePreview = kind;

    const label = document.createElement('p');
    label.className = 'dgi-file-preview__label';

    const media = document.createElement('div');
    media.className = 'dgi-file-preview__media';
    const image = document.createElement('img');
    image.alt = '';
    media.append(image);

    const meta = document.createElement('div');
    meta.className = 'dgi-file-preview__meta';
    const name = document.createElement('strong');
    name.dataset.dgiFileName = '1';
    meta.append(name);

    const actions = document.createElement('div');
    actions.className = 'dgi-file-preview__actions';

    preview.append(label, media, meta, actions);

    return preview;
}

function revokeObjectUrl(input) {
    const url = objectUrls.get(input);
    if (!url) {
        return;
    }

    URL.revokeObjectURL(url);
    objectUrls.delete(input);
}

function setFileIdle(input, idle) {
    input.classList.toggle('dgi-file-input--idle', idle);
    if (idle) {
        input.tabIndex = -1;
        return;
    }

    input.removeAttribute('tabindex');
}

function openFilePicker(input) {
    input.click();
}

function bindUpload(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.dgiUploadBound === '1') {
        return;
    }

    const root = input.closest('.vich-image') || input.parentElement;
    if (!root) {
        return;
    }

    input.dataset.dgiUploadBound = '1';
    const hasCurrent = (input.dataset.dgiCurrentName || '') !== '';
    const currentImage = root.querySelector('img');
    let currentPanel = null;

    if (hasCurrent) {
        currentPanel = createPreview('current');
        currentPanel.querySelector('.dgi-file-preview__label').textContent = 'Image actuelle';
        const image = currentPanel.querySelector('img');
        if (currentImage instanceof HTMLImageElement) {
            const link = currentImage.closest('a');
            if (link && root.contains(link)) {
                link.replaceWith(currentImage);
            }
            image.replaceWith(currentImage);
            const shown = currentPanel.querySelector('img');
            shown.alt = input.dataset.dgiCurrentAlt || 'Image actuellement enregistrée';
        } else {
            currentPanel.querySelector('.dgi-file-preview__media')?.remove();
        }
        currentPanel.querySelector('[data-dgi-file-name]').textContent = input.dataset.dgiCurrentName || '';
        const meta = currentPanel.querySelector('.dgi-file-preview__meta');
        for (const value of [
            input.dataset.dgiCurrentDimensions,
            input.dataset.dgiCurrentSize,
            input.dataset.dgiCurrentMime,
        ]) {
            if (value && value !== '—') {
                meta.append(metaSpan(value));
            }
        }
        currentPanel.querySelector('.dgi-file-preview__actions').append(
            button('Remplacer l’image', 'btn btn-secondary', 'replace'),
        );
        root.append(currentPanel);
        setFileIdle(input, true);
    }

    const pending = createPreview('pending');
    pending.hidden = true;
    const pendingLabel = pending.querySelector('.dgi-file-preview__label');
    const pendingActions = pending.querySelector('.dgi-file-preview__actions');
    if (hasCurrent) {
        pendingLabel.textContent = 'Nouvelle image';
        pendingActions.append(
            button('Choisir une autre image', 'btn btn-secondary', 'change'),
            button('Annuler le remplacement', 'btn btn-outline-secondary', 'cancel'),
        );
    } else {
        pendingLabel.remove();
        pendingActions.append(
            button('Changer l’image', 'btn btn-secondary', 'change'),
            button('Retirer', 'btn btn-outline-secondary', 'clear'),
        );
    }
    root.append(pending);

    const clearPending = () => {
        revokeObjectUrl(input);
        input.value = '';
        const image = pending.querySelector('img');
        image.removeAttribute('src');
        image.alt = '';
        pending.querySelector('[data-dgi-file-name]').textContent = '';
        pending.querySelector('.dgi-file-preview__meta span')?.remove();
        pending.hidden = true;
        if (currentPanel) {
            currentPanel.hidden = false;
            setFileIdle(input, true);
            return;
        }

        setFileIdle(input, false);
    };

    pendingActions.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-dgi-file-action]')
            : null;
        if (!(trigger instanceof HTMLButtonElement)) {
            return;
        }

        const action = trigger.dataset.dgiFileAction;
        if (action === 'change') {
            openFilePicker(input);
            return;
        }

        if (action === 'clear' || action === 'cancel') {
            clearPending();
        }
    });

    currentPanel?.querySelector('[data-dgi-file-action="replace"]')?.addEventListener('click', () => {
        openFilePicker(input);
    });

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }

        revokeObjectUrl(input);
        const url = URL.createObjectURL(file);
        objectUrls.set(input, url);
        const image = pending.querySelector('img');
        image.alt = `Aperçu de ${file.name}`;
        image.src = url;
        pending.querySelector('[data-dgi-file-name]').textContent = file.name;
        const meta = pending.querySelector('.dgi-file-preview__meta');
        meta.querySelector('span')?.remove();
        meta.append(metaSpan(formatWeight(file.size)));
        pending.hidden = false;
        if (currentPanel) {
            currentPanel.hidden = true;
        }
        setFileIdle(input, true);
    });

    input.addEventListener('invalid', () => {
        setFileIdle(input, false);
    });
}

function optionLabel(option) {
    const label = option.getAttribute('data-label');
    if (label) {
        return label;
    }

    return option.textContent.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function mediaEditUrl(select, id) {
    const template = select.dataset.dgiMediaEditTemplate || '';
    if (!template.includes('__ID__') || !/^\d+$/.test(id)) {
        return '';
    }

    return template.split('__ID__').join(id);
}

function syncMediaEditLinks(select) {
    const host = select.nextElementSibling?.classList.contains('ts-wrapper')
        ? select.nextElementSibling
        : select;
    let list = host.nextElementSibling;
    if (!list || list.dataset.dgiMediaEditList !== '1') {
        list = null;
    }

    const selected = [...select.selectedOptions].filter((option) => option.value !== '');
    if (selected.length === 0) {
        list?.remove();
        return;
    }

    if (!list) {
        list = document.createElement('div');
        list.className = 'dgi-media-edit-list';
        list.dataset.dgiMediaEditList = '1';
        host.insertAdjacentElement('afterend', list);
    }

    list.replaceChildren();
    for (const option of selected) {
        const url = mediaEditUrl(select, option.value);
        if (!url) {
            continue;
        }

        const link = document.createElement('a');
        link.className = 'dgi-media-edit';
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        const icon = document.createElement('i');
        icon.className = 'fa fa-pencil';
        icon.setAttribute('aria-hidden', 'true');
        const label = selected.length > 1
            ? `Modifier « ${optionLabel(option)} »`
            : 'Modifier l’image sélectionnée';
        link.append(icon, document.createTextNode(label));
        list.append(link);
    }

    if (!list.childElementCount) {
        list.remove();
    }
}

document.addEventListener('ea.autocomplete.pre-connect', (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || select.dataset.dgiMediaPicker !== '1') {
        return;
    }

    const plugins = event.detail.config.plugins || {};
    if (plugins.clear_button) {
        plugins.clear_button.title = 'Retirer cette image de la sélection';
    }
    if (plugins.remove_button) {
        plugins.remove_button.title = 'Retirer cette image de la sélection';
    }

    const map = snapshot(select);
    const render = event.detail.config.render || {};
    event.detail.config.render = {
        ...render,
        option: (item, escape) => paint(item, escape, map),
        item: (item, escape) => paint(item, escape, map),
    };
});

document.addEventListener('ea.autocomplete.connect', (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || select.dataset.dgiMediaPicker !== '1') {
        return;
    }

    const refresh = () => syncMediaEditLinks(select);
    event.detail.tomSelect.on('change', refresh);
    refresh();
});

document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest('[data-action-name="delete"]')
        : null;
    if (!(trigger instanceof HTMLElement) || trigger.dataset.dgiUsageConfirm === '1') {
        return;
    }

    const labels = [...document.querySelectorAll('.dgi-usage li')]
        .map((item) => item.textContent.trim())
        .filter(Boolean);
    if (labels.length === 0) {
        return;
    }

    const current = trigger.getAttribute('data-action-confirmation-message')
        || 'Cette image sera supprimée de la bibliothèque et des contenus qui l’utilisent ne l’afficheront plus.';
    trigger.setAttribute(
        'data-action-confirmation-message',
        `${current} Elle est utilisée par : ${labels.join(', ')}.`,
    );
    trigger.dataset.dgiUsageConfirm = '1';
}, true);

function bootUploads() {
    document.querySelectorAll('[data-dgi-upload-preview]').forEach((input) => {
        bindUpload(input);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootUploads);
} else {
    bootUploads();
}
