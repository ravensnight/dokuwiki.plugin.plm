/*
 * Client-side editing for the part list.
 * Changes are intentionally not persisted; they disappear on page reload.
 */
(function () {
    'use strict';

    function finishTextEdit(cell, input) {
        cell.textContent = input.value;
        cell.removeAttribute('data-editing');
    }

    function editText(cell) {
        if (cell.dataset.editing) return;

        const input = document.createElement('input');
        input.type = 'text';
        input.value = cell.textContent;
        cell.dataset.editing = 'true';
        cell.replaceChildren(input);
        input.focus();
        input.select();

        input.addEventListener('blur', () => finishTextEdit(cell, input), { once: true });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') input.blur();
            if (event.key === 'Escape') {
                input.value = cell.dataset.originalValue || '';
                input.blur();
            }
        });
    }

    function editCategory(cell, table) {
        if (cell.dataset.editing) return;

        let categories = [];
        try {
            categories = JSON.parse(table.dataset.categoryOptions || '[]');
        } catch (error) {
            return;
        }

        const select = document.createElement('select');
        const emptyOption = new Option('n/a', '');
        select.add(emptyOption);

        categories.forEach((category) => {
            select.add(new Option(category.name, String(category.id)));
        });

        select.value = cell.dataset.value || '';
        cell.dataset.editing = 'true';
        cell.replaceChildren(select);
        select.focus();

        const finish = () => {
            const option = select.selectedOptions[0];
            cell.textContent = option ? option.text : 'n/a';
            cell.dataset.value = select.value;
            cell.removeAttribute('data-editing');
        };

        select.addEventListener('change', finish, { once: true });
        select.addEventListener('blur', finish, { once: true });
    }

    document.addEventListener('click', (event) => {
        const cell = event.target.closest('td.plm-partlist-editable');
        if (!cell) return;

        const table = cell.closest('table.plm-partlist-table');
        if (!table) return;

        cell.dataset.originalValue = cell.textContent;

        if (cell.dataset.editor === 'category') {
            editCategory(cell, table);
        } else {
            editText(cell);
        }
    });
}());

