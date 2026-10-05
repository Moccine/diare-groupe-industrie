document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.dgi-palette input[type="color"]').forEach((input) => {
        const hex = document.createElement('p');
        hex.className = 'dgi-color-hex';
        input.insertAdjacentElement('afterend', hex);

        const field = input.closest('.dgi-color');
        const label = field ? field.querySelector('.form-control-label') : null;
        const swatch = document.createElement('span');
        swatch.className = 'dgi-color-swatch';
        swatch.setAttribute('aria-hidden', 'true');
        if (label) {
            label.prepend(swatch);
        }

        const paint = () => {
            hex.textContent = input.value.toUpperCase();
            swatch.style.backgroundColor = input.value;
        };

        paint();
        input.addEventListener('input', paint);
    });
});
