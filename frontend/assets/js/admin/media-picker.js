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

document.addEventListener('ea.autocomplete.pre-connect', (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || select.dataset.dgiMediaPicker !== '1') {
        return;
    }

    const map = snapshot(select);
    const render = event.detail.config.render || {};
    event.detail.config.render = {
        ...render,
        option: (item, escape) => paint(item, escape, map),
        item: (item, escape) => paint(item, escape, map),
    };
});

document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
        return;
    }

    const holder = input.closest('[data-dgi-upload-preview], .vich-image');
    if (!holder) {
        return;
    }

    const file = input.files?.[0];
    let preview = holder.querySelector('[data-dgi-file-preview]');
    if (!file || !file.type.startsWith('image/')) {
        preview?.remove();
        return;
    }

    if (!preview) {
        preview = document.createElement('p');
        preview.className = 'dgi-file-preview';
        preview.setAttribute('data-dgi-file-preview', '1');
        const image = document.createElement('img');
        image.alt = 'Aperçu du fichier sélectionné';
        preview.append(image);
        input.insertAdjacentElement('afterend', preview);
    }

    const image = preview.querySelector('img');
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    const url = URL.createObjectURL(file);
    image.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
    image.src = url;
});
