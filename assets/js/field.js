/**
 * Mudrava Lucide Field - JavaScript
 *
 * Icon picker for ACF with Lucide + Simple Icons brand icons.
 *
 * - Search metadata is fetched once per page from a REST endpoint (lazy).
 * - Sprites are fetched lazily per source, sanitized against an allowlist,
 *   and injected as <symbol> defs for <use> references.
 * - The options grid is a listbox with roving active option,
 *   aria-activedescendant and infinite pagination.
 *
 * @package Mudrava\LucideField
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    if (typeof acf === 'undefined') {
        return;
    }

    const CONFIG = {
        ICONS_PER_PAGE: 100,
        DEBOUNCE_DELAY: 200,
    };

    const SETTINGS = (typeof window.mudravaLucideField !== 'undefined') ? window.mudravaLucideField : {};

    const OPEN_FIELDS = new Set();
    let documentClickBound = false;
    let uid = 0;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeToken(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function prettifyName(token) {
        const cleaned = normalizeToken(token);

        if (!cleaned) {
            return '';
        }

        const words = cleaned.replace(/-/g, ' ');

        return words.charAt(0).toUpperCase() + words.slice(1);
    }

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

        if (['auto', 'lucide', 'simple', 'custom'].indexOf(source) === -1) {
            source = 'auto';
            name = rawValue;
        }

        return { source: source, name: normalizeToken(name) };
    }

    /**
     * Shared icon data repository, loaded once per page from REST.
     */
    const Repository = {
        status: 'idle',
        promise: null,
        allowedElements: null,
        allowedAttributes: null,
        aliases: {},
        lucide: null,
        simple: null,
        custom: null,
        entries: [],
        lucideMap: {},
        brandMap: {},
        brandAliasMap: {},
        customMap: {},

        ensure: function () {
            if (this.status === 'ready') {
                return Promise.resolve();
            }

            if (this.promise) {
                return this.promise;
            }

            this.status = 'loading';

            const self = this;

            const headers = {};

            if (SETTINGS.nonce) {
                headers['X-WP-Nonce'] = SETTINGS.nonce;
            }

            this.promise = fetch(SETTINGS.dataUrl, {
                credentials: 'same-origin',
                headers: headers,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Icon data request failed: ' + response.status);
                    }

                    return response.json();
                })
                .then(function (data) {
                    self.load(data);
                })
                .catch(function (error) {
                    self.status = 'idle';
                    self.promise = null;
                    throw error;
                });

            return this.promise;
        },

        load: function (data) {
            const self = this;

            this.allowedElements = new Set(data.allowedElements || []);
            this.allowedAttributes = new Set(data.allowedAttributes || []);
            this.aliases = data.compatAliases || {};
            this.lucide = data.lucide || { icons: {}, spriteUrl: '' };
            this.simple = data.simple || { icons: {}, spriteUrl: '' };
            this.custom = data.custom || { icons: {}, symbols: {} };

            this.lucideMap = {};
            this.brandMap = {};
            this.brandAliasMap = {};
            this.customMap = {};

            const entries = [];

            Object.keys(this.lucide.icons || {}).forEach(function (iconName) {
                const tags = self.lucide.icons[iconName] || [];
                const entry = {
                    value: iconName,
                    name: iconName,
                    label: prettifyName(iconName),
                    source: 'lucide',
                    symbol: iconName,
                    tags: tags,
                };

                entry.search = [entry.value, entry.label, (entry.tags || []).join(' ')].join(' ').toLowerCase();
                entries.push(entry);
                self.lucideMap[iconName] = entry;
            });

            Object.keys(this.simple.icons || {}).forEach(function (slug) {
                const tags = self.simple.icons[slug] || [];
                const label = tags[4] || prettifyName(slug);
                const entry = {
                    value: 'simple:' + slug,
                    name: slug,
                    label: label,
                    source: 'simple',
                    symbol: 'simple-' + slug,
                    tags: tags,
                };

                entry.search = [entry.value, entry.label, (entry.tags || []).join(' ')].join(' ').toLowerCase();
                entries.push(entry);
                self.brandMap[slug] = entry;

                [slug, label].concat(tags).forEach(function (tag) {
                    const normalized = normalizeToken(tag);

                    if (normalized && !self.brandAliasMap[normalized]) {
                        self.brandAliasMap[normalized] = entry;
                    }
                });
            });

            Object.keys(this.custom.icons || {}).forEach(function (iconName) {
                const meta = self.custom.icons[iconName] || {};
                const tags = meta.keywords || [];
                const label = meta.label || prettifyName(iconName);
                const entry = {
                    value: 'custom:' + iconName,
                    name: iconName,
                    label: label,
                    source: 'custom',
                    symbol: 'custom-' + iconName,
                    tags: tags,
                };

                entry.search = [entry.value, entry.label, tags.join(' ')].join(' ').toLowerCase();
                entries.push(entry);
                self.customMap[iconName] = entry;
            });

            entries.sort(function (a, b) {
                return a.label.localeCompare(b.label);
            });

            this.entries = entries;
            this.status = 'ready';
        },

        resolveEntry: function (value) {
            const parsed = parseIconValue(value);

            if (!parsed.name) {
                return null;
            }

            if (parsed.source === 'simple') {
                return this.brandMap[parsed.name] || this.brandAliasMap[parsed.name] || null;
            }

            if (parsed.source === 'lucide') {
                return this.lucideMap[parsed.name] || this.resolveAlias(parsed.name);
            }

            if (parsed.source === 'custom') {
                return this.customMap[parsed.name] || null;
            }

            return (
                this.lucideMap[parsed.name] ||
                this.brandMap[parsed.name] ||
                this.brandAliasMap[parsed.name] ||
                this.customMap[parsed.name] ||
                this.resolveAlias(parsed.name)
            );
        },

        resolveAlias: function (name) {
            const replacement = this.aliases[name];

            if (!replacement) {
                return null;
            }

            return this.lucideMap[replacement] || this.brandMap[replacement] || null;
        },
    };

    /**
     * Lazy sprite loader per source. Sprites are sanitized against the
     * server-provided allowlist before being injected into the DOM.
     */
    const Sprites = {
        containers: {},
        promises: {},

        ensure: function (source) {
            if (this.containers[source]) {
                return Promise.resolve();
            }

            if (source === 'custom') {
                const built = this.buildCustom();

                if (!built) {
                    return Promise.reject(new Error('No custom icons'));
                }

                document.body.insertBefore(built, document.body.firstChild);
                this.containers.custom = built;

                return Promise.resolve();
            }

            if (this.promises[source]) {
                return this.promises[source];
            }

            const self = this;
            const sourceData = source === 'simple' ? Repository.simple : Repository.lucide;
            const spriteUrl = (sourceData && sourceData.spriteUrl) || '';

            if (!spriteUrl) {
                return Promise.reject(new Error('Sprite URL not defined'));
            }

            this.promises[source] = fetch(spriteUrl, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Sprite request failed: ' + response.status);
                    }

                    return response.text();
                })
                .then(function (markup) {
                    const container = self.sanitizeSprite(markup, source);

                    if (!container) {
                        throw new Error('Sprite could not be parsed');
                    }

                    document.body.insertBefore(container, document.body.firstChild);
                    self.containers[source] = container;
                })
                .catch(function (error) {
                    self.promises[source] = null;
                    throw error;
                });

            return this.promises[source];
        },

        buildCustom: function () {
            if (this.containers.custom) {
                return null;
            }

            const symbols = (Repository.custom && Repository.custom.symbols) || {};
            const parts = [];

            Object.keys(symbols).forEach(function (name) {
                const symbol = symbols[name];

                if (!symbol || !symbol.inner) {
                    return;
                }

                parts.push('<symbol id="custom-' + escapeHtml(name) + '" viewBox="' + escapeHtml(String(symbol.viewBox || '0 0 24 24')) + '">' + symbol.inner + '</symbol>');
            });

            if (!parts.length) {
                return null;
            }

            return this.sanitizeSprite('<svg xmlns="http://www.w3.org/2000/svg">' + parts.join('') + '</svg>', 'custom');
        },

        ensureAll: function () {
            const self = this;

            return Promise.all([
                this.ensure('lucide').catch(function () { /* preview falls back to text */ }),
                this.ensure('simple').catch(function () { /* preview falls back to text */ }),
                this.ensure('custom').catch(function () { /* no custom icons */ }),
            ]).then(function () {
                if (!self.containers.lucide && !self.containers.simple && !self.containers.custom) {
                    return Promise.reject(new Error('No sprite available'));
                }
            });
        },

        sanitizeSprite: function (markup, source) {
            const doc = new DOMParser().parseFromString(markup, 'image/svg+xml');
            const root = doc.documentElement;

            if (!root || root.nodeName.toLowerCase() !== 'svg' || doc.querySelector('parsererror')) {
                return null;
            }

            const container = document.createElement('div');

            container.setAttribute('id', 'mudrava-lucide-sprite-' + source);
            container.setAttribute('aria-hidden', 'true');
            container.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';

            const namespace = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(namespace, 'svg');
            const allowedElements = Repository.allowedElements;
            const allowedAttributes = Repository.allowedAttributes;
            const prefix = source === 'simple' ? 'simple-' : ( source === 'custom' ? 'custom-' : '' );

            function serializeChildren(node) {
                let out = '';

                Array.prototype.forEach.call(node.children, function (child) {
                    const tag = child.nodeName.toLowerCase();

                    if (!allowedElements.has(tag)) {
                        return;
                    }

                    let attrs = '';

                    Array.prototype.forEach.call(child.attributes, function (attr) {
                        if (!allowedAttributes.has(attr.name)) {
                            return;
                        }

                        attrs += ' ' + attr.name + '="' + escapeHtml(attr.value) + '"';
                    });

                    if (tag === 'g') {
                        const group = serializeChildren(child);

                        if (!group) {
                            return;
                        }

                        out += '<g' + attrs + '>' + group + '</g>';
                    } else {
                        out += '<' + tag + attrs + '/>';
                    }
                });

                return out;
            }

            Array.prototype.forEach.call(root.querySelectorAll('symbol'), function (symbol) {
                const id = symbol.getAttribute('id');

                if (!id || id.indexOf(prefix) !== 0 || !/^[a-zA-Z0-9_-]+$/.test(id)) {
                    return;
                }

                const inner = serializeChildren(symbol);

                if (!inner) {
                    return;
                }

                const clone = document.createElementNS(namespace, 'symbol');
                clone.setAttribute('id', id);
                clone.setAttribute('viewBox', symbol.getAttribute('viewBox') || '0 0 24 24');
                clone.innerHTML = inner;
                svg.appendChild(clone);
            });

            container.appendChild(svg);

            return container;
        },
    };

    /**
     * Lucide Icon Field Type
     */
    const LucideIconField = acf.Field.extend({
        type: 'lucide_icon',

        filteredIcons: [],
        currentPage: 0,
        searchQuery: '',
        searchTimer: null,
        loadedIcons: new Set(),
        activeIndex: -1,

        events: {
            'click .mudrava-lucide-selected': 'onToggle',
            'keydown .mudrava-lucide-selected': 'onSelectedKeydown',
            'click .mudrava-lucide-clear': 'onClear',
            'click .mudrava-lucide-icon': 'onOptionClick',
            'input .mudrava-lucide-search': 'onSearch',
            'keydown .mudrava-lucide-search': 'onSearchKeydown',
            'focusin .mudrava-lucide-search': 'onSearchFocus',
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

        initialize: function () {
            if (!this.$input().attr('id')) {
                this.$input().attr('id', 'mudrava-lucide-field-' + (uid++));
            }

            this.filteredIcons = [];
            this.currentPage = 0;
            this.loadedIcons = new Set();
            this.activeIndex = -1;

            this.updatePreview();
            this.bindScroll();

            if (!documentClickBound) {
                documentClickBound = true;

                $(document).on('click.mudravaLucideGlobal', function (e) {
                    OPEN_FIELDS.forEach(function (field) {
                        if (!$(e.target).closest(field.$el).length) {
                            field.closeDropdown();
                        }
                    });
                });
            }
        },

        /**
         * Render the selected icon preview once data and sprites resolve.
         */
        updatePreview: function () {
            const self = this;
            const value = this.$input().val();

            if (!value) {
                const $preview0 = this.$('.mudrava-lucide-preview');
                const $selected0 = this.$selected();

                $selected0.removeClass('is-unknown');
                $preview0.html('<span class="mudrava-lucide-preview-empty">' + escapeHtml(SETTINGS.emptyLabel || 'No icon selected') + '</span>');
                $selected0.find('.mudrava-lucide-clear').remove();
                return;
            }

            this.$selected().removeClass('is-unknown');

            Repository.ensure()
                .then(function () {
                    const $preview = self.$('.mudrava-lucide-preview');
                    const $selected = self.$selected();
                    const entry = Repository.resolveEntry(value);
                    const label = entry ? entry.label : value;
                    const $label = $preview.find('.mudrava-lucide-preview-name');

                    if (!$label.length) {
                        $preview.html('<span class="mudrava-lucide-preview-name">' + escapeHtml(label) + '</span>');
                    } else {
                        $label.text(label);
                    }

                    self.showClearButton();

                    if (!entry) {
                        $selected.addClass('is-unknown').attr('title', SETTINGS.unknownNotice || '');
                        return null;
                    }

                    return Sprites.ensure(entry.source).then(function () {
                        const svg = self.createIconSvg(entry, 24);
                        const $svg = $preview.find('svg.mudrava-lucide-icon-svg');

                        if ($svg.length) {
                            $svg.replaceWith(svg);
                        } else {
                            $preview.prepend(svg);
                        }
                    });
                })
                .catch(function () {
                    const $preview = self.$('.mudrava-lucide-preview');
                    const $label = $preview.find('.mudrava-lucide-preview-name');

                    if (!$label.length) {
                        $preview.html('<span class="mudrava-lucide-preview-name">' + escapeHtml(value) + '</span>');
                    } else {
                        $label.text(value);
                    }

                    self.showClearButton();
                });
        },

        createIconSvg: function (entry, size) {
            if (!entry) {
                return '';
            }

            const safeSize = parseInt(size, 10) || 24;
            const safeSymbol = escapeHtml(entry.symbol);
            const brand = entry.source === 'simple';
            const custom = entry.source === 'custom';
            const variant = custom ? 'custom' : ( brand ? 'brand' : 'lucide' );
            const paint = custom ? '' : ' fill="' + ( brand ? 'currentColor' : 'none' ) + '" stroke="' + ( brand ? 'none' : 'currentColor' ) + '"';
            const geometry = brand || custom ? '' : ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

            return '<svg class="mudrava-lucide-icon-svg mudrava-lucide-icon-svg--' + variant + '" xmlns="http://www.w3.org/2000/svg" width="' + safeSize + '" height="' + safeSize + '" viewBox="0 0 24 24"' + paint + geometry + ' aria-hidden="true" focusable="false"><use href="#' + safeSymbol + '"></use></svg>';
        },

        showClearButton: function () {
            const $selected = this.$selected();
            const value = this.$input().val();
            const allowNull = this.$control().data('allow-null') === 1;

            if (value && allowNull && !$selected.find('.mudrava-lucide-clear').length) {
                const clearLabel = SETTINGS.clear || '';

                $selected.append(
                    '<button type="button" class="mudrava-lucide-clear" title="' + escapeHtml(clearLabel) + '" aria-label="' + escapeHtml(clearLabel) + '">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                    '</button>'
                );
            }
        },

        bindScroll: function () {
            const self = this;

            this.scrollTimer = null;

            this.$gridWrap().on('scroll.mudravaLucide', function () {
                if (self.scrollTimer) {
                    clearTimeout(self.scrollTimer);
                }

                self.scrollTimer = setTimeout(function () {
                    self.checkLoadMore();
                }, 100);
            });
        },

        announce: function (message) {
            this.$('.mudrava-lucide-live').text(message);
        },

        checkLoadMore: function () {
            const $wrap = this.$gridWrap();

            if (!$wrap.length || !$wrap[0]) {
                return;
            }

            const scrollTop = $wrap.scrollTop();
            const scrollHeight = $wrap[0].scrollHeight;
            const clientHeight = $wrap[0].clientHeight;

            if (scrollTop + clientHeight >= scrollHeight - 100) {
                this.loadMoreIcons();
            }
        },

        loadMoreIcons: function () {
            const totalPages = Math.ceil(this.filteredIcons.length / CONFIG.ICONS_PER_PAGE);

            if (this.currentPage < totalPages - 1) {
                this.currentPage++;
                this.renderOptionsPage(this.currentPage, true);
            }
        },

        renderOptionsPage: function (page, append) {
            const $grid = this.$grid();
            const startIdx = page * CONFIG.ICONS_PER_PAGE;
            const endIdx = Math.min(startIdx + CONFIG.ICONS_PER_PAGE, this.filteredIcons.length);
            const iconsToRender = this.filteredIcons.slice(startIdx, endIdx);
            const currentValue = this.$input().val();
            const currentEntry = Repository.resolveEntry(currentValue);
            const self = this;

            if (!append) {
                $grid.empty();
                this.loadedIcons.clear();
            }

            const fragment = document.createDocumentFragment();

            iconsToRender.forEach(function (entry) {
                if (self.loadedIcons.has(entry.value)) {
                    return;
                }

                const option = document.createElement('div');

                option.className = 'mudrava-lucide-icon';
                option.tabIndex = -1;
                option.setAttribute('role', 'option');
                option.dataset.icon = entry.value;
                option.setAttribute('aria-label', entry.label);

                const isCurrent = currentEntry ? currentEntry.value === entry.value : entry.value === currentValue;

                option.setAttribute('aria-selected', isCurrent ? 'true' : 'false');

                if (isCurrent) {
                    option.classList.add('is-selected');
                }

                const iconWrap = document.createElement('span');

                iconWrap.className = 'mudrava-lucide-icon-preview';
                iconWrap.innerHTML = self.createIconSvg(entry, 22);
                option.appendChild(iconWrap);

                const label = document.createElement('span');

                label.className = 'mudrava-lucide-icon-label';
                label.textContent = entry.label;
                option.appendChild(label);

                fragment.appendChild(option);

                self.loadedIcons.add(entry.value);
            });

            $grid.append(fragment);
        },

        renderIcons: function () {
            this.currentPage = 0;
            this.loadedIcons.clear();
            this.activeIndex = this.filteredIcons.length ? 0 : -1;
            this.$grid().empty();

            if (this.filteredIcons.length === 0) {
                this.$noResults().show();
                this.updateActivedescendant();
                this.announce(SETTINGS.noResults || '');
                return;
            }

            this.$noResults().hide();
            this.renderOptionsPage(0, false);
            this.updateActivedescendant();

            if (SETTINGS.resultsLabel) {
                this.announce(SETTINGS.resultsLabel.replace('%d', String(this.filteredIcons.length)));
            }
        },

        options: function () {
            return this.$grid().children('.mudrava-lucide-icon');
        },

        updateActivedescendant: function () {
            const $options = this.options();
            const $search = this.$search();

            $options.removeClass('is-active');

            if (this.activeIndex >= 0 && this.activeIndex < $options.length) {
                const $active = $options.eq(this.activeIndex);

                $active.addClass('is-active');

                let id = $active.attr('id');

                if (!id) {
                    id = this.optionId(this.activeIndex);
                }

                if (id) {
                    $search.attr('aria-activedescendant', id);
                } else {
                    $search.removeAttr('aria-activedescendant');
                }
            } else {
                $search.removeAttr('aria-activedescendant');
            }
        },

        optionId: function (index) {
            const $options = this.options();

            if (index < 0 || index >= $options.length) {
                return '';
            }

            let id = $options.eq(index).attr('id');

            if (!id) {
                id = this.$input().attr('id') + '-option-' + index;
                $options.eq(index).attr('id', id);
            }

            return id;
        },

        setActive: function (index, scroll) {
            const total = this.filteredIcons.length;

            if (total === 0) {
                this.activeIndex = -1;
                this.updateActivedescendant();
                return;
            }

            if (index < 0) {
                index = 0;
            }

            if (index > total - 1) {
                index = total - 1;
            }

            // Ensure the page containing the target option is rendered
            // before clamping; options map 1:1 to filteredIcons positions.
            const requiredPage = Math.floor(index / CONFIG.ICONS_PER_PAGE);

            while (this.currentPage < requiredPage && this.currentPage < Math.ceil(total / CONFIG.ICONS_PER_PAGE) - 1) {
                this.currentPage++;
                this.renderOptionsPage(this.currentPage, true);
            }

            this.activeIndex = Math.min(index, this.options().length - 1);

            this.updateActivedescendant();

            const node = this.options().eq(index)[0];

            if (scroll && node && node.scrollIntoView) {
                node.scrollIntoView({ block: 'nearest' });
            }
        },

        openDropdown: function () {
            const self = this;

            OPEN_FIELDS.add(this);

            Repository.ensure()
                .then(function () {
                    self.$control().addClass('is-open');
                    self.$selected().attr('aria-expanded', 'true');
                    self.$search().val('').attr('aria-expanded', 'true');
                    self.searchQuery = '';
                    self.filteredIcons = Repository.entries.slice();
                    self.renderIcons();

                    Sprites.ensureAll().then(function () {
                        if (self.activeIndex >= 0) {
                            self.setActive(self.activeIndex, true);
                        }
                    }).catch(function () { /* icons stay as placeholders until a sprite loads */ });

                    setTimeout(function () {
                        self.$search().trigger('focus');
                        self.scrollToSelected();
                    }, 50);
                })
                .catch(function () {
                    OPEN_FIELDS.delete(self);
                    self.$noResults().show();
                });
        },

        closeDropdown: function () {
            OPEN_FIELDS.delete(this);
            this.$control().removeClass('is-open');
            this.$selected().attr('aria-expanded', 'false');
            this.$search().attr('aria-expanded', 'false');
        },

        isOpen: function () {
            return this.$control().hasClass('is-open');
        },

        scrollToSelected: function () {
            const $selected = this.$grid().find('.is-selected');

            if ($selected.length) {
                const node = $selected[0];

                if (node.scrollIntoView) {
                    node.scrollIntoView({ block: 'nearest' });
                }
            }
        },

        onToggle: function (e) {
            if ($(e.target).closest('.mudrava-lucide-clear').length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            if (this.isOpen()) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        },

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

        onSearchFocus: function () {
            const self = this;

            if (!this.isOpen()) {
                OPEN_FIELDS.add(this);

                Repository.ensure().then(function () {
                    self.$control().addClass('is-open');
                    self.$selected().attr('aria-expanded', 'true');
                    self.$search().attr('aria-expanded', 'true');
                    self.filteredIcons = Repository.entries.slice();
                    self.renderIcons();
                    Sprites.ensureAll().catch(function () { /* placeholders remain until sprite loads */ });
                }).catch(function () {
                    OPEN_FIELDS.delete(self);
                });
            }
        },

        onOptionClick: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $option = $(e.currentTarget);

            this.selectOption($option.data('icon'));
        },

        selectOption: function (value) {
            const $input = this.$input();
            const current = $input.val();

            if (current !== value) {
                $input.val(value).trigger('change');
                acf.doAction('change', $input);
            }

            this.$grid().find('.is-selected').removeClass('is-selected').attr('aria-selected', 'false');

            if (value) {
                this.$grid().find('.mudrava-lucide-icon').filter(function () {
                    return $(this).data('icon') === value;
                }).addClass('is-selected').attr('aria-selected', 'true');
            }

            this.updatePreview();
            this.closeDropdown();

            if (value) {
                const entry = Repository.resolveEntry(value);
                const label = entry ? entry.label : value;

                this.announce(SETTINGS.selectedLabel ? SETTINGS.selectedLabel.replace('%s', label) : label);
            } else {
                this.announce(SETTINGS.emptyLabel || '');
            }
        },

        onClear: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $input = this.$input();

            $input.val('').trigger('change');
            acf.doAction('change', $input);

            this.updatePreview();
            this.announce(SETTINGS.emptyLabel || '');
        },

        onSearch: function (e) {
            const self = this;
            const value = e.target.value;

            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }

            this.searchTimer = setTimeout(function () {
                self.searchTimer = null;
                self.applyFilter(value);
            }, CONFIG.DEBOUNCE_DELAY);
        },

        onSearchKeydown: function (e) {
            const keys = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', 'Escape'];

            if (keys.indexOf(e.key) === -1) {
                return;
            }

            e.preventDefault();

            if (e.key === 'Escape') {
                this.closeDropdown();
                this.$selected().trigger('focus');
                return;
            }

            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
                this.searchTimer = null;
                this.applyFilter(this.$search().val());
            }

            if (e.key === 'ArrowDown') {
                this.setActive(this.activeIndex + 1, true);
            } else if (e.key === 'ArrowUp') {
                this.setActive(this.activeIndex - 1, true);
            } else if (e.key === 'Home') {
                this.setActive(0, true);
            } else if (e.key === 'End') {
                this.setActive(this.filteredIcons.length - 1, true);
            } else if (e.key === 'Enter') {
                const entry = this.filteredIcons[this.activeIndex];

                if (entry) {
                    this.selectOption(entry.value);
                }
            }
        },

        applyFilter: function (query) {
            const normalizedQuery = String(query || '').toLowerCase().trim();

            this.searchQuery = normalizedQuery;

            if (!normalizedQuery) {
                this.filteredIcons = Repository.entries.slice();
            } else {
                this.filteredIcons = Repository.entries
                    .filter(function (entry) {
                        return entry.search.indexOf(normalizedQuery) !== -1;
                    })
                    .map(function (entry) {
                        return { entry: entry, score: getSearchScore(entry, normalizedQuery) };
                    })
                    .sort(function (a, b) {
                        if (a.score !== b.score) {
                            return a.score - b.score;
                        }

                        return a.entry.label.localeCompare(b.entry.label);
                    })
                    .map(function (item) {
                        return item.entry;
                    });
            }

            this.renderIcons();
        },

        remove: function () {
            OPEN_FIELDS.delete(this);
            this.$gridWrap().off('scroll.mudravaLucide');

            if (this.scrollTimer) {
                clearTimeout(this.scrollTimer);
                this.scrollTimer = null;
            }

            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
                this.searchTimer = null;
            }
        },
    });

    /**
     * Score search results so direct name/title matches appear before tag-only matches.
     */
    function getSearchScore(entry, query) {
        const normalizedQuery = normalizeToken(query);
        const normalizedName = normalizeToken(entry.name);
        const normalizedLabel = normalizeToken(entry.label);
        const normalizedValue = normalizeToken(entry.value.replace(/^(?:simple|custom):/, ''));
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
    }

    acf.registerFieldType(LucideIconField);

})(jQuery);
