/**
 * Mudrava Lucide Field - JavaScript
 *
 * Handles the Lucide icon picker field functionality with
 * local sprite injection and virtual scrolling for performance.
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
     * Sprite management - loads once per page.
     */
    let spriteLoaded = false;
    let spriteLoadPromise = null;

    /**
     * Load and inject the sprite SVG into the page.
     *
     * @return {Promise<void>}
     */
    function loadSprite() {
        if (spriteLoaded) {
            return Promise.resolve();
        }

        if (spriteLoadPromise) {
            return spriteLoadPromise;
        }

        spriteLoadPromise = new Promise((resolve, reject) => {
            const spriteUrl = mudravaLucideField.spriteUrl;

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
                        .attr('id', 'mudrava-lucide-sprite')
                        .css({
                            position: 'absolute',
                            width: 0,
                            height: 0,
                            overflow: 'hidden',
                            visibility: 'hidden',
                        })
                        .html(data);

                    $('body').prepend($container);
                    spriteLoaded = true;
                    resolve();
                },
                error: function (xhr, status, error) {
                    reject(new Error('Failed to load sprite: ' + error));
                },
            });
        });

        return spriteLoadPromise;
    }

    /**
     * Lucide Icon Field Type
     */
    const LucideIconField = acf.Field.extend({
        type: 'lucide_icon',

        allIconNames: [],
        filteredIconNames: [],
        currentPage: 0,
        searchTimer: null,
        loadedIcons: new Set(),

        events: {
            'click .mudrava-lucide-selected': 'onToggle',
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
            const icons = mudravaLucideField.icons || {};
            this.allIconNames = Object.keys(icons);
            this.filteredIconNames = [...this.allIconNames];
            this.currentPage = 0;
            this.loadedIcons = new Set();

            this.updatePreviewIcon();
            this.bindDocumentClick();
            this.bindScroll();
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

            loadSprite().then(function () {
                const $preview = self.$('.mudrava-lucide-preview');
                $preview.html(self.createIconSvg(value, 24) + '<span class="mudrava-lucide-preview-name">' + value + '</span>');
                self.showClearButton();
            });
        },

        /**
         * Create an SVG element with proper Lucide styling.
         */
        createIconSvg: function (iconName, size) {
            return '<svg class="mudrava-lucide-icon-svg" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#' + iconName + '"></use></svg>';
        },

        /**
         * Show clear button if there's a value.
         */
        showClearButton: function () {
            const $selected = this.$selected();
            const value = this.$input().val();

            if (value && !$selected.find('.mudrava-lucide-clear').length) {
                $selected.append(
                    '<button type="button" class="mudrava-lucide-clear" title="' + mudravaLucideField.clear + '">' +
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
            const totalPages = Math.ceil(this.filteredIconNames.length / CONFIG.ICONS_PER_PAGE);

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
            const endIdx = Math.min(startIdx + CONFIG.ICONS_PER_PAGE, this.filteredIconNames.length);
            const iconsToRender = this.filteredIconNames.slice(startIdx, endIdx);
            const currentValue = this.$input().val();
            const self = this;

            if (!append) {
                $grid.empty();
                this.loadedIcons.clear();
            }

            const fragment = document.createDocumentFragment();

            iconsToRender.forEach(function (iconName) {
                if (self.loadedIcons.has(iconName)) {
                    return;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mudrava-lucide-icon';
                button.dataset.icon = iconName;
                button.title = iconName;

                if (iconName === currentValue) {
                    button.classList.add('is-selected');
                }

                button.innerHTML = self.createIconSvg(iconName, 22);
                fragment.appendChild(button);

                self.loadedIcons.add(iconName);
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

            if (this.filteredIconNames.length === 0) {
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
         * Open dropdown.
         */
        openDropdown: function () {
            const self = this;

            loadSprite().then(function () {
                self.$control().addClass('is-open');
                self.$search().val('');
                self.filteredIconNames = [...self.allIconNames];
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

            $input.val(value).trigger('change');

            this.$grid().find('.is-selected').removeClass('is-selected');

            if (value) {
                $preview.html(
                    this.createIconSvg(value, 24) +
                    '<span class="mudrava-lucide-preview-name">' + value + '</span>'
                );

                const $iconButton = this.$grid().find('[data-icon="' + value + '"]');
                $iconButton.addClass('is-selected');

                if (!$selected.find('.mudrava-lucide-clear').length) {
                    $selected.append(
                        '<button type="button" class="mudrava-lucide-clear" title="' + mudravaLucideField.clear + '">' +
                        '<span class="dashicons dashicons-no-alt"></span>' +
                        '</button>'
                    );
                }
            } else {
                $preview.html(
                    '<span class="mudrava-lucide-preview-empty">No icon selected</span>'
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
            const icons = mudravaLucideField.icons || {};
            const normalizedQuery = query.toLowerCase().trim();

            if (!normalizedQuery) {
                this.filteredIconNames = [...this.allIconNames];
            } else {
                this.filteredIconNames = this.allIconNames.filter(function (iconName) {
                    const tags = icons[iconName] || [];
                    const searchString = (iconName + ' ' + tags.join(' ')).toLowerCase();
                    return searchString.includes(normalizedQuery);
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
