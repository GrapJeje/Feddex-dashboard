class Header {
    constructor() {
        this.initTheme();
        this.initFilters();
        this.setupEventListeners();
    }

    initTheme() {
        this.html = document.documentElement;
        this.darkModeToggle = document.getElementById('darkModeToggle');

        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const currentTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

        this.html.setAttribute('data-theme', currentTheme);
        if (this.darkModeToggle) {
            this.darkModeToggle.checked = currentTheme === 'dark';
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                const newTheme = e.matches ? 'dark' : 'light';
                this.html.setAttribute('data-theme', newTheme);
                if (this.darkModeToggle) {
                    this.darkModeToggle.checked = newTheme === 'dark';
                }
            }
        });
    }

    initFilters() {
        this.controlsToggle = document.getElementById('controlsToggle');
        this.expandedControls = document.getElementById('expandedControls');

        if (this.controlsToggle && this.expandedControls) {
            this.controlsToggle.addEventListener('click', () => {
                const isExpanded = this.controlsToggle.getAttribute('aria-expanded') === 'true';
                this.controlsToggle.setAttribute('aria-expanded', !isExpanded);
                this.controlsToggle.classList.toggle('active');
                this.expandedControls.classList.toggle('active');
            });
        }

        document.querySelectorAll('.toggle-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const parent = e.currentTarget.parentElement;

                if (e.currentTarget.dataset.filter === parent.querySelector('.toggle-btn').dataset.filter) {
                    parent.querySelectorAll('.toggle-btn').forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-pressed', 'false');
                    });
                }

                e.currentTarget.classList.toggle('active');
                e.currentTarget.setAttribute('aria-pressed',
                    e.currentTarget.classList.contains('active'));
            });
        });
    }

    setupEventListeners() {
        // Theme toggle event
        if (this.darkModeToggle) {
            this.darkModeToggle.addEventListener('change', () => {
                const newTheme = this.darkModeToggle.checked ? 'dark' : 'light';
                this.html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new Header();
});
