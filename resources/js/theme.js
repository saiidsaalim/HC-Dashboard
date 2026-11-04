const storageKey = 'hc-dashboard-theme';
const validPreferences = new Set(['light', 'dark', 'system']);
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

const storedPreference = () => {
    try {
        const value = window.localStorage.getItem(storageKey);

        return validPreferences.has(value) ? value : 'system';
    } catch {
        return 'system';
    }
};

const resolvedTheme = (preference) => (
    preference === 'system'
        ? (systemTheme.matches ? 'dark' : 'light')
        : preference
);

const updateControls = (preference) => {
    document.querySelectorAll('[data-theme-option]').forEach((option) => {
        const isActive = option.dataset.themeOption === preference;
        option.setAttribute('aria-checked', String(isActive));
        option.querySelector('[data-theme-check]')?.classList.toggle('invisible', !isActive);
    });

    document.querySelectorAll('[data-theme-label]').forEach((label) => {
        label.textContent = {
            light: 'Mode siang',
            dark: 'Mode malam',
            system: 'Ikuti sistem',
        }[preference];
    });
};

export const applyTheme = (preference, persist = false) => {
    const normalizedPreference = validPreferences.has(preference) ? preference : 'system';
    const theme = resolvedTheme(normalizedPreference);
    const root = document.documentElement;

    root.classList.toggle('dark', theme === 'dark');
    root.dataset.theme = normalizedPreference;
    root.style.colorScheme = theme;

    if (persist) {
        try {
            window.localStorage.setItem(storageKey, normalizedPreference);
        } catch {
            // The selected theme remains active for the current page when storage is unavailable.
        }
    }

    updateControls(normalizedPreference);
    window.dispatchEvent(new CustomEvent('themechange', {
        detail: { preference: normalizedPreference, theme },
    }));
};

export const initializeThemeControls = () => {
    const preference = storedPreference();
    applyTheme(preference);

    document.querySelectorAll('[data-theme-option]').forEach((option) => {
        option.addEventListener('click', () => {
            applyTheme(option.dataset.themeOption, true);
        });
    });

    systemTheme.addEventListener('change', () => {
        if (storedPreference() === 'system') {
            applyTheme('system');
        }
    });
};
