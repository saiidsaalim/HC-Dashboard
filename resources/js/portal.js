const unitButtons = document.querySelectorAll('[data-unit-select]');
const workspacePanels = document.querySelectorAll('[data-workspace-panel]');
const loginDialog = document.querySelector('[data-login-dialog]');

unitButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const selectedUnit = button.dataset.unitSelect;

        unitButtons.forEach((unitButton) => {
            unitButton.setAttribute('aria-pressed', String(unitButton === button));
        });

        workspacePanels.forEach((panel) => {
            panel.hidden = panel.dataset.workspacePanel !== selectedUnit;
        });
    });
});

if (loginDialog instanceof HTMLDialogElement) {
    let activeTrigger = null;

    const openLoginDialog = (trigger, workspaceName = '') => {
        activeTrigger = trigger;
        const workspaceLabel = loginDialog.querySelector('[data-current-workspace]');

        workspaceLabel.textContent = workspaceName;
        workspaceLabel.hidden = workspaceName.length === 0;

        if (!loginDialog.open) {
            loginDialog.showModal();
        }

        loginDialog.querySelector('[name="email"]').focus();
    };

    document.querySelectorAll('[data-open-login]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openLoginDialog(trigger, trigger.dataset.workspaceName ?? '');
        });
    });

    loginDialog.querySelector('[data-close-login]').addEventListener('click', () => {
        loginDialog.close();
    });

    loginDialog.addEventListener('click', (event) => {
        if (event.target === loginDialog) {
            loginDialog.close();
        }
    });

    loginDialog.addEventListener('close', () => {
        activeTrigger?.focus();
        activeTrigger = null;
    });

    if (loginDialog.dataset.autoOpen === 'true') {
        openLoginDialog(null);
    }
}
