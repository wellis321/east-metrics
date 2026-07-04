(function () {
    function initPeerPicker(root) {
        var toggle = root.querySelector('[data-peer-toggle]');
        var panel = root.querySelector('[data-peer-panel]');
        var closeBtn = root.querySelector('[data-peer-close]');
        var header = root.querySelector('[data-peer-header]');
        var search = root.querySelector('[data-peer-search]');
        var list = root.querySelector('[data-peer-list]');
        var chips = root.querySelector('[data-peer-chips]');
        if (!toggle || !panel) {
            return;
        }

        var items = list.querySelectorAll('.peer-picker-item');
        var defaultToggleText = toggle.textContent;

        // Both the chip list and the toggle button's label live outside the
        // panel's own layout (the button sits on the page, the chips inside
        // the panel), so nothing about the main page ever changes size no
        // matter how many landlords are selected — only the button's text
        // updates, in place.
        function updateSelection() {
            chips.innerHTML = '';
            var count = 0;
            items.forEach(function (item) {
                var box = item.querySelector('input');
                if (!box.checked) {
                    return;
                }
                count++;
                var chip = document.createElement('span');
                chip.className = 'peer-chip';
                chip.textContent = item.textContent.trim();
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = '×';
                remove.setAttribute('aria-label', 'Remove ' + item.textContent.trim());
                remove.addEventListener('click', function () {
                    box.checked = false;
                    updateSelection();
                });
                chip.appendChild(remove);
                chips.appendChild(chip);
            });
            toggle.textContent = count > 0
                ? count + ' landlord' + (count === 1 ? '' : 's') + ' selected'
                : defaultToggleText;
        }

        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            items.forEach(function (item) {
                item.style.display = item.dataset.name.indexOf(q) !== -1 ? '' : 'none';
            });
        });

        items.forEach(function (item) {
            item.querySelector('input').addEventListener('change', updateSelection);
        });

        updateSelection();

        function openPanel() {
            panel.hidden = false;
            search.focus();
        }

        function closePanel() {
            panel.hidden = true;
        }

        toggle.addEventListener('click', function () {
            panel.hidden ? openPanel() : closePanel();
        });
        closeBtn.addEventListener('click', closePanel);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) {
                closePanel();
            }
        });

        // Drag-to-move: mousedown on the header switches from the default
        // centred position to explicit pixel coordinates, then tracks the
        // pointer until mouseup. The rest of the page never reflows since
        // the panel is position:fixed throughout.
        var dragging = false;
        var offsetX = 0;
        var offsetY = 0;

        header.addEventListener('mousedown', function (e) {
            dragging = true;
            var rect = panel.getBoundingClientRect();
            panel.style.left = rect.left + 'px';
            panel.style.top = rect.top + 'px';
            panel.style.right = 'auto';
            panel.style.transform = 'none';
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging) {
                return;
            }
            var maxX = window.innerWidth - panel.offsetWidth;
            var maxY = window.innerHeight - panel.offsetHeight;
            var x = Math.min(Math.max(0, e.clientX - offsetX), Math.max(0, maxX));
            var y = Math.min(Math.max(0, e.clientY - offsetY), Math.max(0, maxY));
            panel.style.left = x + 'px';
            panel.style.top = y + 'px';
        });

        document.addEventListener('mouseup', function () {
            if (dragging) {
                dragging = false;
                document.body.style.userSelect = '';
            }
        });
    }

    document.querySelectorAll('[data-peer-picker]').forEach(initPeerPicker);
})();
