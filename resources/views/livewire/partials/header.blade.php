<header class="site-header">
    @vite(['resources/sass/partials/header.scss', 'resources/js/header.js'])

    <div class="header-container">
        <div class="logo-container">
            <a href="/" aria-label="Feddex Home">
                <img src="{{ asset('img/FeddEx.png') }}" alt="Feddex Logo" class="logo" width="120" height="40">
            </a>
        </div>

        <div class="controls-container">
            <button class="controls-toggle" id="controlsToggle" aria-expanded="false">
                <svg class="filter-icon" viewBox="0 0 20 20" width="20" height="20">
                    <path
                        d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/>
                </svg>
                <svg class="close-icon" viewBox="0 0 20 20" width="20" height="20">
                    <path
                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
                </svg>
                <span class="toggle-text">Filters</span>
            </button>

            <div class="search-filter">
                <input type="text" placeholder="Track & Trace of plaatsnaam..." id="packageSearch">
                <button class="search-button" aria-label="Search">
                    <span class="search-text">Zoeken</span>
                    <svg class="search-icon" viewBox="0 0 20 20" width="20" height="20">
                        <path
                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/>
                    </svg>
                </button>
            </div>

            <div class="expanded-controls" id="expandedControls">
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Bestemming</label>
                        <div class="toggle-buttons">
                            <button class="toggle-btn active" data-filter="destination" data-value="domestic">
                                Binnenland
                            </button>
                            <button class="toggle-btn" data-filter="destination" data-value="international">Buitenland
                            </button>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Bezorgmoment</label>
                        <div class="toggle-buttons">
                            <button class="toggle-btn" data-filter="delivery_day" data-value="weekend">Weekend</button>
                            <button class="toggle-btn" data-filter="delivery_day" data-value="weekday">Door de week
                            </button>
                            <button class="toggle-btn" data-filter="delivery_time" data-value="day">Overdag</button>
                            <button class="toggle-btn" data-filter="delivery_time" data-value="evening">'s Avonds
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sort-section">
                    <div class="filter-group">
                        <label for="sortSelect">Sorteren op</label>
                        <select class="sort-select" id="sortSelect">
                            <option value="">Standaard</option>
                            <option value="size-asc">Formaat (A-Z)</option>
                            <option value="size-desc">Formaat (Z-A)</option>
                            <option value="weight-asc">Gewicht (Laag-Hoog)</option>
                            <option value="weight-desc">Gewicht (Hoog-Laag)</option>
                            <option value="days-asc">Dagen onderweg (Min-Max)</option>
                            <option value="days-desc">Dagen onderweg (Max-Min)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="theme-toggle-container">
            <label class="theme-toggle-label" aria-label="Toggle dark mode">
                <input type="checkbox" id="darkModeToggle" class="theme-toggle-checkbox">
                <span class="theme-toggle-slider">
            <svg class="slider-icon sun" viewBox="0 0 24 24">
                <path
                    d="M12 18a6 6 0 110-12 6 6 0 010 12zm0-2a4 4 0 100-8 4 4 0 000 8zM11 1h2v3h-2V1zm0 19h2v3h-2v-3zM3.515 4.929l1.414-1.414L7.05 5.636 5.636 7.05 3.515 4.93zM16.95 18.364l1.414-1.414 2.121 2.121-1.414 1.414-2.121-2.121zm2.121-14.85l1.414 1.415-2.121 2.121-1.414-1.414 2.121-2.121zM5.636 16.95l1.414 1.414-2.121 2.121-1.414-1.414 2.121-2.121zM1 11h3v2H1v-2zm19 0h3v2h-3v-2z"/>
            </svg>
            <svg class="slider-icon moon" viewBox="0 0 24 24">
                <path
                    d="M10 7a7 7 0 0012 4.9v.1c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2h.1A6.98 6.98 0 0010 7zm-6 5a8 8 0 0015.062 3.762A9 9 0 018.238 4.938 7.999 7.999 0 004 12z"/>
            </svg>
            <span class="slider-knob"></span>
        </span>
            </label>
        </div>
    </div>
</header>
