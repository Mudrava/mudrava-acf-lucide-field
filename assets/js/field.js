/**
 * Mudrava Lucide Field - JavaScript
 *
 * Handles the icon picker field functionality with local sprite injection
 * and virtual scrolling for performance.
 *
 * @package Mudrava\LucideField
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    if (typeof acf === 'undefined') {
        return;
    }

    /**
     * Configuration constants.
     */
    const CONFIG = {
        ICONS_PER_PAGE: 100,
        DEBOUNCE_DELAY: 200,
    };

    /**
     * Escape dynamic text inserted into HTML strings.
     */
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Escape dynamic attribute values inserted into HTML strings.
     */
    function escapeAttr(value) {
        return escapeHtml(value);
    }

    /**
     * Normalize labels for alias matching.
     */
    function normalizeToken(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    /**
     * Parse stored icon values.
     */
    function parseIconValue(value) {
        const rawValue = String(value || '').trim();

        if (!rawValue) {
            return { source: '', name: '' };
        }

        let source = 'auto';
        let name = rawValue;
        const matches = rawValue.match(/^([a-z]+):(.+)$/i);

        if (matches) {
            source = matches[1].toLowerCase();
            name = matches[2];
        }

        if (source === 'brand') {
            source = 'simple';
        }

        if (!['auto', 'lucide', 'simple'].includes(source)) {
            source = 'auto';
            name = rawValue;
        }

        return {
            source: source,
            name: normalizeToken(name),
        };
    }

    /**
     * Sprite management - loads once per page.
     */
    const loadedSprites = {};
    const spriteLoadPromises = {};

    /**
     * Load and inject a sprite SVG into the page.
     *
     * @return {Promise<void>}
     */
    function loadSprite(spriteUrl, containerId) {
        if (loadedSprites[containerId] || $('#' + containerId).length) {
            loadedSprites[containerId] = true;
            return Promise.resolve();
        }

        if (spriteLoadPromises[containerId]) {
            return spriteLoadPromises[containerId];
        }

        spriteLoadPromises[containerId] = new Promise((resolve, reject) => {
            if (!spriteUrl) {
                reject(new Error('Sprite URL not defined'));
                return;
            }

            $.ajax({
                url: spriteUrl,
                dataType: 'text',
                cache: true,
                success: function (data) {
                    const $container = $('<div>')
                        .attr('id', containerId)
                        .css({
                            position: 'absolute',
                            width: 0,
                            height: 0,
                            overflow: 'hidden',
                            visibility: 'hidden',
                        })
                        .html(data);

                    $('body').prepend($container);
                    loadedSprites[containerId] = true;
                    resolve();
                },
                error: function (xhr, status, error) {
                    reject(new Error('Failed to load sprite: ' + error));
                },
            });
        });

        return spriteLoadPromises[containerId];
    }

    /**
     * Load all bundled sprites.
     *
     * @return {Promise<void[]>}
     */
    function loadSprites() {
        return Promise.all([
            loadSprite(mudravaLucideField.spriteUrl, 'mudrava-lucide-sprite'),
            loadSprite(mudravaLucideField.brandSpriteUrl, 'mudrava-simple-icons-sprite'),
        ]);
    }

    /**
     * Lucide Icon Field Type
     */
    const LucideIconField = acf.Field.extend({
        type: 'lucide_icon',

        allIcons: [],
        filteredIcons: [],
        iconMap: {},
        brandMap: {},
        brandAliasMap: {},
        currentPage: 0,
        searchQuery: '',
        searchTimer: null,
        loadedIcons: new Set(),

        events: {
            'click .mudrava-lucide-selected': 'onToggle',
            'keydown .mudrava-lucide-selected': 'onSelectedKeydown',
            'click .mudrava-lucide-clear': 'onClear',
            'click .mudrava-lucide-icon': 'onSelect',
            'input .mudrava-lucide-search': 'onSearch',
            'keydown .mudrava-lucide-search': 'onSearchKeydown',
        },

        $control: function () {
            return this.$('.mudrava-lucide-picker');
        },

        $selected: function () {
            return this.$('.mudrava-lucide-selected');
        },

        $dropdown: function () {
            return this.$('.mudrava-lucide-dropdown');
        },

        $grid: function () {
            return this.$('.mudrava-lucide-grid');
        },

        $search: function () {
            return this.$('.mudrava-lucide-search');
        },

        $input: function () {
            return this.$('.mudrava-lucide-input');
        },

        $noResults: function () {
            return this.$('.mudrava-lucide-no-results');
        },

        $gridWrap: function () {
            return this.$('.mudrava-lucide-grid-wrap');
        },

        /**
         * Initialize the field.
         */
        initialize: function () {
            this.buildIconIndex();
            this.filteredIcons = [...this.allIcons];
            this.currentPage = 0;
            this.loadedIcons = new Set();

            this.updatePreviewIcon();
            this.bindDocumentClick();
            this.bindScroll();
        },

        /**
         * Build searchable icon entries.
         */
        buildIconIndex: function () {
            const icons = mudravaLucideField.icons || {};
            const brandIcons = mudravaLucideField.brandIcons || {};
            const entries = [];
            const self = this;

            this.iconMap = {};
            this.brandMap = {};
            this.brandAliasMap = {};

            Object.keys(icons).forEach(function (iconName) {
                const tags = icons[iconName] || [];
                const entry = {
                    value: iconName,
                    name: iconName,
                    label: iconName,
                    source: 'lucide',
                    symbol: iconName,
                    tags: tags,
                };

                entry.search = self.createSearchString(entry);
                entries.push(entry);
                self.iconMap[iconName] = entry;
            });

            Object.keys(brandIcons).forEach(function (slug) {
                const tags = brandIcons[slug] || [];
                const label = tags[4] || slug;
                const entry = {
                    value: 'simple:' + slug,
                    name: slug,
                    label: label,
                    source: 'simple',
                    symbol: 'simple-' + slug,
                    tags: tags,
                };

                entry.search = self.createSearchString(entry);
                entries.push(entry);
                self.brandMap[slug] = entry;

                [slug, label].concat(tags).forEach(function (tag) {
                    const normalized = normalizeToken(tag);

                    if (normalized && !self.brandAliasMap[normalized]) {
                        self.brandAliasMap[normalized] = entry;
                    }
                });
            });

            this.allIcons = entries;
        },

        /**
         * Create the searchable text for an icon entry.
         */
        createSearchString: function (entry) {
            return [
                entry.value,
                entry.name,
                entry.label,
                entry.source,
                entry.source === 'simple' ? 'brand logo simple-icons' : 'lucide',
                (entry.tags || []).join(' '),
            ].join(' ').toLowerCase();
        },

        /**
         * Score search results so direct name/title matches appear before tag-only matches.
         */
        getSearchScore: function (entry, query) {
            const normalizedQuery = normalizeToken(query);
            const normalizedName = normalizeToken(entry.name);
            const normalizedLabel = normalizeToken(entry.label);
            const normalizedValue = normalizeToken(entry.value.replace(/^simple:/, ''));
            const tags = (entry.tags || []).map(normalizeToken);

            if (!normalizedQuery) {
                return 100;
            }

            if (normalizedName === normalizedQuery || normalizedLabel === normalizedQuery || normalizedValue === normalizedQuery) {
                return entry.source === 'simple' ? 0 : 5;
            }

            if (normalizedName.indexOf(normalizedQuery) === 0 || normalizedLabel.indexOf(normalizedQuery) === 0 || normalizedValue.indexOf(normalizedQuery) === 0) {
                return entry.source === 'simple' ? 10 : 20;
            }

            if (tags.some(function (tag) { return tag === normalizedQuery; })) {
                return entry.source === 'simple' ? 30 : 60;
            }

            if (tags.some(function (tag) { return tag.indexOf(normalizedQuery) === 0; })) {
                return entry.source === 'simple' ? 40 : 70;
            }

            return entry.source === 'simple' ? 50 : 80;
        },

        /**
         * Resolve stored values, including legacy unprefixed brand values.
         */
        resolveIconEntry: function (value) {
            const parsed = parseIconValue(value);

            if (!parsed.name) {
                return null;
            }

            if (parsed.source === 'simple') {
                return this.brandMap[parsed.name] || this.brandAliasMap[parsed.name] || null;
            }

            if (parsed.source === 'lucide') {
                return this.iconMap[parsed.name] || null;
            }

            return this.iconMap[parsed.name] || this.brandMap[parsed.name] || this.brandAliasMap[parsed.name] || null;
        },

        /**
         * Compare picker entry with the current stored value.
         */
        isCurrentEntry: function (entry, currentValue) {
            const currentEntry = this.resolveIconEntry(currentValue);

            return currentEntry ? currentEntry.value === entry.value : entry.value === currentValue;
        },

        /**
         * Update the preview icon if a value exists.
         */
        updatePreviewIcon: function () {
            const value = this.$input().val();

            if (!value) {
                return;
            }

            const self = this;

            loadSprites().then(function () {
                const $preview = self.$('.mudrava-lucide-preview');
                const entry = self.resolveIconEntry(value);
                const iconSvg = entry ? self.createIconSvg(entry, 24) : '';
                const label = entry ? entry.label : value;

                $preview.html(iconSvg + '<span class="mudrava-lucide-preview-name">' + escapeHtml(label) + '</span>');
                self.showClearButton();
            }).catch(function () {
                const $preview = self.$('.mudrava-lucide-preview');
                $preview.html('<span class="mudrava-lucide-preview-name">' + escapeHtml(value) + '</span>');
                self.showClearButton();
            });
        },

        /**
         * Create an SVG element with source-specific styling.
         */
        createIconSvg: function (icon, size) {
            const entry = typeof icon === 'string' ? this.resolveIconEntry(icon) : icon;
            const safeSize = parseInt(size, 10) || 24;

            if (!entry) {
                return '';
            }

            const safeSymbol = escapeAttr(entry.symbol);

            if (entry.source === 'simple') {
                return '<svg class="mudrava-lucide-icon-svg mudrava-lucide-icon-svg--brand" width="' + safeSize + '" height="' + safeSize + '" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" focusable="false"><use href="#' + safeSymbol + '"></use></svg>';
            }

            return '<svg class="mudrava-lucide-icon-svg mudrava-lucide-icon-svg--lucide" width="' + safeSize + '" height="' + safeSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#' + safeSymbol + '"></use></svg>';
        },

        /**
         * Show clear button if there's a value and allow_null is true.
         */
        showClearButton: function () {
            const $selected = this.$selected();
            const value = this.$input().val();
            const allowNull = this.$control().data('allow-null') == 1;

            if (value && allowNull && !$selected.find('.mudrava-lucide-clear').length) {
                $selected.append(
                    '<button type="button" class="mudrava-lucide-clear" title="' + escapeAttr(mudravaLucideField.clear) + '" aria-label="' + escapeAttr(mudravaLucideField.clear) + '">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                    '</button>'
                );
            }
        },

        /**
         * Bind document click for closing dropdown.
         */
        bindDocumentClick: function () {
            const self = this;

            $(document).on('click.mudravaLucide' + this.cid, function (e) {
                if (!$(e.target).closest('.mudrava-lucide-picker').length) {
                    self.closeDropdown();
                }
            });
        },

        /**
         * Bind scroll for infinite loading.
         */
        bindScroll: function () {
            const self = this;
            let scrollTimer = null;

            this.$gridWrap().on('scroll.mudravaLucide', function () {
                if (scrollTimer) {
                    clearTimeout(scrollTimer);
                }

                scrollTimer = setTimeout(function () {
                    self.checkLoadMore();
                }, 100);
            });
        },

        /**
         * Check if we need to load more icons.
         */
        checkLoadMore: function () {
            const $wrap = this.$gridWrap();
            const scrollTop = $wrap.scrollTop();
            const scrollHeight = $wrap[0].scrollHeight;
            const clientHeight = $wrap[0].clientHeight;

            if (scrollTop + clientHeight >= scrollHeight - 100) {
                this.loadMoreIcons();
            }
        },

        /**
         * Load more icons (next page).
         */
        loadMoreIcons: function () {
            const totalPages = Math.ceil(this.filteredIcons.length / CONFIG.ICONS_PER_PAGE);

            if (this.currentPage < totalPages - 1) {
                this.currentPage++;
                this.renderIconsPage(this.currentPage, true);
            }
        },

        /**
         * Render a specific page of icons.
         */
        renderIconsPage: function (page, append) {
            const $grid = this.$grid();
            const startIdx = page * CONFIG.ICONS_PER_PAGE;
            const endIdx = Math.min(startIdx + CONFIG.ICONS_PER_PAGE, this.filteredIcons.length);
            const iconsToRender = this.filteredIcons.slice(startIdx, endIdx);
            const currentValue = this.$input().val();
            const self = this;

            if (!append) {
                $grid.empty();
                this.loadedIcons.clear();
            }

            $grid.toggleClass('has-search', this.searchQuery.length > 0);

            const fragment = document.createDocumentFragment();

            iconsToRender.forEach(function (entry) {
                if (self.loadedIcons.has(entry.value)) {
                    return;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mudrava-lucide-icon';
                button.dataset.icon = entry.value;
                button.title = entry.label;
                button.setAttribute('aria-label', entry.label);
                button.setAttribute('role', 'option');

                if (self.isCurrentEntry(entry, currentValue)) {
                    button.classList.add('is-selected');
                    button.setAttribute('aria-selected', 'true');
                } else {
                    button.setAttribute('aria-selected', 'false');
                }

                button.innerHTML = self.createIconSvg(entry, 22) + '<span class="mudrava-lucide-icon-label">' + escapeHtml(entry.label) + '</span>';
                fragment.appendChild(button);

                self.loadedIcons.add(entry.value);
            });

            $grid.append(fragment);
        },

        /**
         * Render initial icons.
         */
        renderIcons: function () {
            this.currentPage = 0;
            this.loadedIcons.clear();
            this.$grid().empty();
            this.$grid().toggleClass('has-search', this.searchQuery.length > 0);

            if (this.filteredIcons.length === 0) {
                this.$noResults().show();
                return;
            }

            this.$noResults().hide();
            this.renderIconsPage(0, false);
        },

        /**
         * Toggle dropdown.
         */
        onToggle: function (e) {
            e.preventDefault();
            e.stopPropagation();

            if ($(e.target).closest('.mudrava-lucide-clear').length) {
                return;
            }

            const $control = this.$control();

            if ($control.hasClass('is-open')) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        },

        /**
         * Toggle dropdown from keyboard on the selected control.
         */
        onSelectedKeydown: function (e) {
            if ($(e.target).closest('.mudrava-lucide-clear').length) {
                return;
            }

            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                this.openDropdown();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.closeDropdown();
            }
        },

        /**
         * Open dropdown.
         */
        openDropdown: function () {
            const self = this;

            loadSprites().then(function () {
                self.$control().addClass('is-open');
                self.$selected().attr('aria-expanded', 'true');
                self.$search().val('');
                self.searchQuery = '';
                self.filteredIcons = [...self.allIcons];
                self.renderIcons();

                setTimeout(function () {
                    self.$search().focus();
                    self.scrollToSelected();
                }, 50);
            });
        },

        /**
         * Close dropdown.
         */
        closeDropdown: function () {
            this.$control().removeClass('is-open');
            this.$selected().attr('aria-expanded', 'false');
        },

        /**
         * Scroll to selected icon.
         */
        scrollToSelected: function () {
            const $selected = this.$grid().find('.is-selected');

            if ($selected.length) {
                const $gridWrap = this.$gridWrap();
                const scrollTop = $selected.position().top - 50;
                $gridWrap.scrollTop(scrollTop);
            }
        },

        /**
         * Handle icon selection.
         */
        onSelect: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $icon = $(e.currentTarget);
            const iconName = $icon.data('icon');

            this.setValue(iconName);
            this.closeDropdown();
        },

        /**
         * Set field value.
         */
        setValue: function (value) {
            const $input = this.$input();
            const $preview = this.$('.mudrava-lucide-preview');
            const $selected = this.$selected();
            const entry = this.resolveIconEntry(value);
            const label = entry ? entry.label : value;

            $input.val(value).trigger('change');

            this.$grid().find('.is-selected').removeClass('is-selected').attr('aria-selected', 'false');

            if (value) {
                $preview.html(
                    this.createIconSvg(entry || value, 24) +
                    '<span class="mudrava-lucide-preview-name">' + escapeHtml(label) + '</span>'
                );

                const $iconButton = this.$grid().find('.mudrava-lucide-icon').filter(function () {
                    return $(this).data('icon') === value;
                });
                $iconButton.addClass('is-selected').attr('aria-selected', 'true');

                if (!$selected.find('.mudrava-lucide-clear').length) {
                    $selected.append(
                        '<button type="button" class="mudrava-lucide-clear" title="' + escapeAttr(mudravaLucideField.clear) + '" aria-label="' + escapeAttr(mudravaLucideField.clear) + '">' +
                        '<span class="dashicons dashicons-no-alt"></span>' +
                        '</button>'
                    );
                }
            } else {
                $preview.html(
                    '<span class="mudrava-lucide-preview-empty">' + escapeHtml(mudravaLucideField.emptyLabel || 'No icon selected') + '</span>'
                );
                $selected.find('.mudrava-lucide-clear').remove();
            }

            acf.doAction('change', $input);
        },

        /**
         * Clear selection.
         */
        onClear: function (e) {
            e.preventDefault();
            e.stopPropagation();
            this.setValue('');
        },

        /**
         * Handle search with debounce.
         */
        onSearch: function (e) {
            const self = this;

            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }

            this.searchTimer = setTimeout(function () {
                self.filterIcons(e.target.value);
            }, CONFIG.DEBOUNCE_DELAY);
        },

        /**
         * Handle search keydown.
         */
        onSearchKeydown: function (e) {
            if (e.key === 'Escape') {
                this.closeDropdown();
                this.$selected().focus();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const $firstVisible = this.$grid().find('.mudrava-lucide-icon').first();
                if ($firstVisible.length) {
                    $firstVisible.trigger('click');
                }
            }
        },

        /**
         * Filter icons by search query.
         */
        filterIcons: function (query) {
            const self = this;
            const normalizedQuery = query.toLowerCase().trim();
            this.searchQuery = normalizedQuery;

            if (!normalizedQuery) {
                this.filteredIcons = [...this.allIcons];
            } else {
                this.filteredIcons = this.allIcons.filter(function (entry) {
                    return entry.search.includes(normalizedQuery);
                }).sort(function (a, b) {
                    const scoreDifference = self.getSearchScore(a, normalizedQuery) - self.getSearchScore(b, normalizedQuery);

                    if (scoreDifference !== 0) {
                        return scoreDifference;
                    }

                    return a.label.localeCompare(b.label);
                });
            }

            this.renderIcons();
        },

        /**
         * Cleanup.
         */
        remove: function () {
            $(document).off('click.mudravaLucide' + this.cid);
            this.$gridWrap().off('scroll.mudravaLucide');

            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }
        },
    });

    acf.registerFieldType(LucideIconField);

})(jQuery);
