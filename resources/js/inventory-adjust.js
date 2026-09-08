function parseAdjustmentValue(raw) {
    const trimmed = String(raw ?? '').trim();

    if (trimmed === '' || trimmed === '+' || trimmed === '-') {
        return 0;
    }

    const parsed = Number.parseInt(trimmed, 10);

    return Number.isFinite(parsed) ? parsed : 0;
}

function formatAdjustmentValue(num) {
    if (num === 0) {
        return '';
    }

    return String(num);
}

function stepInput(input, delta) {
    const next = parseAdjustmentValue(input.value) + delta;
    input.value = formatAdjustmentValue(next);
}

function bindAdjustStepButton(button) {
    button.addEventListener('click', () => {
        const form = button.closest('form');

        if (! form) {
            return;
        }

        const input = form.querySelector('[name="quantity_change"]');

        if (! input) {
            return;
        }

        const delta = Number.parseInt(button.dataset.adjustStep ?? '0', 10);

        if (! Number.isFinite(delta) || delta === 0) {
            return;
        }

        stepInput(input, delta);
        input.focus();
    });
}

function bindAdjustInput(input) {
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            stepInput(input, 1);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            stepInput(input, -1);
        }
    });
}

export function initInventoryAdjust(root = document) {
    root.querySelectorAll('[data-adjust-step]').forEach(bindAdjustStepButton);

    root.querySelectorAll('form').forEach((form) => {
        if (! form.querySelector('[data-adjust-step]')) {
            return;
        }

        const input = form.querySelector('[name="quantity_change"]');

        if (input) {
            bindAdjustInput(input);
        }
    });
}

export default initInventoryAdjust;
