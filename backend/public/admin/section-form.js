(function () {
    function start() {
        var select = document.querySelector('select[name="Section[type]"]');
        if (!select) {
            return;
        }

        var root = document.querySelector('[data-section-hints]');
        var hints = {};
        if (root) {
            try {
                hints = JSON.parse(root.getAttribute('data-section-hints') || '{}');
            } catch (error) {
                hints = {};
            }
        }

        function apply(type) {
            document.querySelectorAll('.dgi-sec-field').forEach(function (field) {
                var allowed = [];
                field.classList.forEach(function (className) {
                    if (className.indexOf('dgi-when-') === 0) {
                        allowed.push(className.slice('dgi-when-'.length));
                    }
                });
                var show = allowed.length === 0 || allowed.indexOf(type) !== -1;
                field.hidden = !show;
                var next = field.nextElementSibling;
                if (next && next.classList.contains('flex-fill')) {
                    next.hidden = !show;
                }
            });

            document.querySelectorAll('.dgi-sec-group').forEach(function (group) {
                var body = group.querySelector('.form-fieldset-body .row');
                if (!body) {
                    return;
                }
                var fields = Array.prototype.filter.call(body.children, function (child) {
                    return !child.classList.contains('flex-fill');
                });
                if (fields.length === 0) {
                    return;
                }
                group.hidden = fields.every(function (field) {
                    return field.hidden;
                });
            });

            var live = document.querySelector('.dgi-type-live');
            if (live) {
                live.textContent = hints[type] || '';
            }
        }

        apply(select.value || 'custom');
        select.addEventListener('change', function () {
            apply(select.value || 'custom');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
