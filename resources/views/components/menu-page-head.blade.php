{{--
    Shared head for the Option A / Option B layouts of the Settings and Reports pages: the Inter and
    Noto Nastaliq Urdu fonts, a few styles, and the Alpine state every layout uses (live search,
    "/" to focus, the group in view, and a per-user list kept in localStorage for pins or
    recently used links).
--}}
@once
    @push('header')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">
        <style>
            .menu-page { font-family: Inter, ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
            .menu-ur { font-family: 'Noto Nastaliq Urdu', serif; direction: rtl; line-height: 2; }
            .menu-noscroll { scrollbar-width: none; }
            .menu-noscroll::-webkit-scrollbar { display: none; }
        </style>
        <script>
            /**
             * Alpine state for a grouped menu page.
             *
             * allChip: the page has an "All" chip, so no group is in view until the first one reaches the top.
             *
             * Config: storageKey (localStorage key or null), limit (how many ids to keep), ids (every link on the
             * page), groups (group keys in order), allChip.
             */
            window.menuPage = function ({ storageKey = null, limit = null, ids = [], groups = [], allChip = false } = {}) {
                return {
                    query: '',
                    saved: [],
                    active: allChip ? null : (groups[0] ?? null),
                    selected: groups[0] ?? null,
                    mobileOpen: false,
                    scrollLockUntil: 0,
                    jumped: null,

                    init() {
                        this.saved = this.load().filter((id) => ids.includes(id));

                        if (this.$root.querySelector('[data-menu-section]') && this.$root.hasAttribute('data-menu-spy')) {
                            let queued = false;
                            window.addEventListener('scroll', () => {
                                if (queued) {
                                    return;
                                }
                                queued = true;
                                requestAnimationFrame(() => { queued = false; this.spy(); });
                            }, { passive: true });
                            this.$watch('active', (key) => this.revealChip(key));
                            this.$watch('query', () => this.$nextTick(() => this.spy()));
                        }
                    },

                    words() {
                        return this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
                    },

                    matches(el) {
                        const search = el.dataset.search || '';

                        return this.words().every((word) => search.includes(word));
                    },

                    groupVisible(section) {
                        return Array.from(section.querySelectorAll('[data-menu-item]')).some((item) => this.matches(item));
                    },

                    groupHasMatches(key) {
                        const section = this.$root.querySelector('[data-menu-section="' + key + '"]');

                        return section ? this.groupVisible(section) : false;
                    },

                    visibleCount() {
                        return Array.from(this.$root.querySelectorAll('[data-menu-item]')).filter((item) => this.matches(item)).length;
                    },

                    focusSearch(event) {
                        if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) && !event.target.isContentEditable) {
                            event.preventDefault();
                            this.$refs.search.focus();
                        }
                    },

                    clearSearch() {
                        this.query = '';
                        this.$refs.search.blur();
                    },

                    load() {
                        if (!storageKey) {
                            return [];
                        }
                        try {
                            const value = JSON.parse(window.localStorage.getItem(storageKey) || '[]');

                            return Array.isArray(value) ? value.filter((id) => typeof id === 'string') : [];
                        } catch (error) {
                            return [];
                        }
                    },

                    store() {
                        if (!storageKey) {
                            return;
                        }
                        try {
                            window.localStorage.setItem(storageKey, JSON.stringify(this.saved));
                        } catch (error) {
                            // Storage can be blocked (private mode); the page still works without it.
                        }
                    },

                    remember(id) {
                        this.saved = [id, ...this.saved.filter((saved) => saved !== id)].slice(0, limit ?? ids.length);
                        this.store();
                    },

                    isSaved(id) {
                        return this.saved.includes(id);
                    },

                    rank(id) {
                        return this.saved.indexOf(id);
                    },

                    toggle(id) {
                        this.saved = this.isSaved(id) ? this.saved.filter((saved) => saved !== id) : [...this.saved, id];
                        this.store();
                    },

                    jump(key) {
                        const section = this.$root.querySelector('[data-menu-section="' + key + '"]');
                        if (!section) {
                            return;
                        }
                        this.active = key;
                        this.jumped = key;
                        this.scrollLockUntil = Date.now() + 900;
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },

                    toTop() {
                        this.active = null;
                        this.scrollLockUntil = Date.now() + 900;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },

                    spy() {
                        if (Date.now() < this.scrollLockUntil) {
                            return;
                        }
                        const sections = Array.from(this.$root.querySelectorAll('[data-menu-section]')).filter((section) => section.offsetParent !== null);
                        if (sections.length === 0) {
                            return;
                        }
                        const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
                        let current = allChip ? null : sections[0];
                        for (const section of sections) {
                            if (section.getBoundingClientRect().top <= 180) {
                                current = section;
                            }
                        }
                        const jumpedTo = sections.find((section) => section.dataset.menuSection === this.jumped);
                        const jumpedInView = jumpedTo && jumpedTo.getBoundingClientRect().top >= 0 && jumpedTo.getBoundingClientRect().top < window.innerHeight;
                        let inView = current;
                        if (atBottom && window.scrollY > 0) {
                            inView = jumpedInView ? jumpedTo : sections[sections.length - 1];
                        }
                        this.active = inView ? inView.dataset.menuSection : null;
                    },

                    revealChip(key) {
                        const row = this.$refs.chips;
                        const chip = row ? row.querySelector('[data-chip="' + key + '"]') : null;
                        if (row && chip && row.scrollWidth > row.clientWidth) {
                            row.scrollTo({ left: Math.max(0, chip.offsetLeft - 16) });
                        }
                    },

                    open(key) {
                        this.selected = key;
                        this.mobileOpen = true;
                        this.$nextTick(() => {
                            if (window.innerWidth < 1024 && this.$refs.pane) {
                                this.$refs.pane.scrollIntoView({ block: 'start' });
                            }
                        });
                    },

                    back() {
                        this.mobileOpen = false;
                        this.$nextTick(() => {
                            if (this.$refs.groups) {
                                this.$refs.groups.scrollIntoView({ block: 'start' });
                            }
                        });
                    },
                };
            };
        </script>
    @endpush
@endonce
