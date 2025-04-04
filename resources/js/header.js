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
        this.packageSearch = document.getElementById('packageSearch');

        if (this.controlsToggle && this.expandedControls) {
            this.controlsToggle.addEventListener('click', () => {
                const isExpanded = this.controlsToggle.getAttribute('aria-expanded') === 'true';
                this.controlsToggle.setAttribute('aria-expanded', !isExpanded);
                this.controlsToggle.classList.toggle('active');
                this.expandedControls.classList.toggle('active');
            });
        }

        this.initializeFiltersFromURL();

        document.querySelectorAll('.toggle-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const group = e.currentTarget.closest('.toggle-buttons');

                if (group) {
                    group.querySelectorAll('.toggle-btn').forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-pressed', 'false');
                    });
                }

                e.currentTarget.classList.toggle('active');
                e.currentTarget.setAttribute('aria-pressed',
                    e.currentTarget.classList.contains('active'));

                this.updateURLWithFilters();
            });
        });

        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this.updateURLWithFilters();
            });
        }

        if (this.packageSearch) {
            const searchButton = document.querySelector('.search-button');
            if (searchButton) {
                searchButton.addEventListener('click', () => {
                    this.updateURLWithFilters();
                });
            }

            this.packageSearch.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.updateURLWithFilters();
                }
            });
        }
    }

    initializeFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);

        document.querySelectorAll('.toggle-btn').forEach(button => {
            const filterType = button.dataset.filter;
            const filterValue = button.dataset.value;

            if (urlParams.has(filterType) && urlParams.get(filterType).includes(filterValue)) {
                button.classList.add('active');
                button.setAttribute('aria-pressed', 'true');
            }
        });

        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect && urlParams.has('sort')) {
            sortSelect.value = urlParams.get('sort');
        }

        if (this.packageSearch && urlParams.has('search')) {
            this.packageSearch.value = urlParams.get('search');
        }
    }

    setupEventListeners() {
        if (this.darkModeToggle) {
            this.darkModeToggle.addEventListener('change', () => {
                const newTheme = this.darkModeToggle.checked ? 'dark' : 'light';
                this.html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }
    }

    updateURLWithFilters() {
        const url = new URL(window.location.href);
        const searchParams = new URLSearchParams();

        url.search = '';

        document.querySelectorAll('.toggle-btn.active').forEach(button => {
            const filterType = button.dataset.filter;
            const filterValue = button.dataset.value;
            searchParams.set(filterType, filterValue);
        });

        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect && sortSelect.value) {
            searchParams.set('sort', sortSelect.value);
        }

        if (this.packageSearch && this.packageSearch.value.trim() !== '') {
            searchParams.set('search', this.packageSearch.value.trim());
        }

        window.location.href = `${url.pathname}?${searchParams.toString()}`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new Header();
});
