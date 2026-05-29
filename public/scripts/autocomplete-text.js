(function () {
    const FIELD_SELECTOR = '[data-autocomplete-source][data-autocomplete-mode]';
    const DEFAULT_ENDPOINTS = {
        tags: '/autocomplete/tags',
        series: '/autocomplete/series',
    };
    const initializedFields = new WeakSet();
    const fieldStates = new WeakMap();

    let dropdown = null;
    let activeField = null;

    function ensureDropdown() {
        if (dropdown) {
            return dropdown;
        }

        dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.setAttribute('role', 'listbox');
        dropdown.hidden = true;
        document.body.appendChild(dropdown);

        dropdown.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        return dropdown;
    }

    function initField(field) {
        if (initializedFields.has(field)) {
            return;
        }

        initializedFields.add(field);
        field.setAttribute('autocomplete', 'off');
        field.setAttribute('aria-autocomplete', 'list');
        field.setAttribute('aria-expanded', 'false');

        fieldStates.set(field, {
            timer: null,
            abortController: null,
            results: [],
            activeIndex: 0,
            query: '',
            suppressNextInputFetch: false,
        });

        field.addEventListener('input', () => {
            const state = fieldStates.get(field);

            if (state.suppressNextInputFetch) {
                state.suppressNextInputFetch = false;
                return;
            }

            scheduleFetch(field);
        });
        field.addEventListener('focus', () => scheduleFetch(field));
        field.addEventListener('click', () => scheduleFetch(field));
        field.addEventListener('keydown', (event) => handleKeydown(field, event));
        field.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (!dropdown?.contains(document.activeElement)) {
                    closeDropdown(field);
                }
            }, 120);
        });
    }

    function initFields() {
        ensureDropdown();
        document.querySelectorAll(FIELD_SELECTOR).forEach(initField);
    }

    function scheduleFetch(field) {
        const state = fieldStates.get(field);
        const query = currentQuery(field);

        window.clearTimeout(state.timer);

        if (query === '') {
            state.query = '';
            state.results = [];
            state.activeIndex = -1;

            state.abortController?.abort();
            state.abortController = null;

            closeDropdown(field);
            return;
        }

        state.query = query;
        state.timer = window.setTimeout(() => fetchSuggestions(field, query), 180);
    }

    async function fetchSuggestions(field, query) {
        const state = fieldStates.get(field);
        const endpoint = autocompleteEndpoint(field);

        if (!endpoint) {
            closeDropdown(field);
            return;
        }

        if (state.abortController) {
            state.abortController.abort();
        }

        const abortController = new AbortController();
        state.abortController = abortController;

        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('q', query);

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: abortController.signal,
            });

            if (!response.ok) {
                closeDropdown(field);
                return;
            }

            const results = await response.json();

            if (state.query !== query) {
                return;
            }

            renderDropdown(field, Array.isArray(results) ? results : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                closeDropdown(field);
            }
        }
    }

    function renderDropdown(field, results) {
        const state = fieldStates.get(field);

        state.results = results;
        state.activeIndex = results.length > 0 ? 0 : -1;

        if (results.length === 0) {
            closeDropdown(field);
            return;
        }

        activeField = field;
        field.setAttribute('aria-expanded', 'true');
        dropdown.innerHTML = '';

        results.forEach((result, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'autocomplete-option';
            option.setAttribute('role', 'option');
            option.dataset.index = String(index);
            option.setAttribute('aria-selected', index === state.activeIndex ? 'true' : 'false');

            const label = document.createElement('span');
            label.className = 'autocomplete-option__label';
            label.textContent = result.label || result.value || '';

            const count = document.createElement('span');
            count.className = 'autocomplete-option__count';
            count.textContent = typeof result.count === 'number' ? String(result.count) : '';

            option.append(label, count);
            option.addEventListener('click', () => selectResult(field, index));
            dropdown.appendChild(option);
        });

        positionDropdown(field);
        dropdown.hidden = false;
    }

    function handleKeydown(field, event) {
        if (activeField !== field || dropdown.hidden) {
            return;
        }

        const state = fieldStates.get(field);

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex(field, state.activeIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex(field, state.activeIndex - 1);
            return;
        }

        if (event.key === 'Enter' || event.key === 'Tab') {
            if (state.activeIndex >= 0) {
                event.preventDefault();
                selectResult(field, state.activeIndex);
            }

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeDropdown(field);
        }
    }

    function setActiveIndex(field, nextIndex) {
        const state = fieldStates.get(field);
        const total = state.results.length;

        if (total === 0) {
            state.activeIndex = -1;
            return;
        }

        state.activeIndex = (nextIndex + total) % total;

        dropdown.querySelectorAll('.autocomplete-option').forEach((option, index) => {
            const isActive = index === state.activeIndex;
            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (isActive) {
                option.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function selectResult(field, index) {
        const state = fieldStates.get(field);
        const result = state.results[index];

        if (!result) {
            return;
        }

        if (field.dataset.autocompleteMode === 'csv') {
            insertCsvValue(field, result.value);
        } else {
            field.value = result.value;
            setCaret(field, field.value.length);
            state.suppressNextInputFetch = true;
        }

        dispatchFieldChange(field);
        closeDropdown(field);
        field.focus();
    }

    function insertCsvValue(field, value) {
        const selectionStart = field.selectionStart ?? field.value.length;
        const range = csvTokenRange(field.value, selectionStart);
        const before = field.value.slice(0, range.start);
        const after = field.value.slice(range.end).replace(/^,\s*/, '');
        const leadingSpace = before !== '' && before.endsWith(',') && !/\s$/.test(before) ? ' ' : '';
        const insertion = leadingSpace + quoteCsvValue(value) + ', ';

        field.value = before + insertion + after;
        setCaret(field, (before + insertion).length);
    }

    function csvTokenRange(value, caret) {
        let start = 0;
        let end = value.length;
        let inQuotes = false;

        for (let index = 0; index < value.length; index += 1) {
            const character = value[index];

            if (character === '"') {
                if (inQuotes && value[index + 1] === '"') {
                    index += 1;
                    continue;
                }

                inQuotes = !inQuotes;
                continue;
            }

            if (character === ',' && !inQuotes) {
                if (index < caret) {
                    start = index + 1;
                    continue;
                }

                end = index;
                break;
            }
        }

        return { start, end };
    }

    function currentQuery(field) {
        if (field.dataset.autocompleteMode !== 'csv') {
            return field.value.trim();
        }

        const selectionStart = field.selectionStart ?? field.value.length;
        const range = csvTokenRange(field.value, selectionStart);
        const token = field.value
            .slice(range.start, range.end)
            .trim()
            .replace(/^"/, '')
            .replace(/"$/, '')
            .replace(/""/g, '"');

        return token.trim();
    }

    function quoteCsvValue(value) {
        const text = String(value);
        const needsQuotes = /[,"\n\r]/.test(text);

        return needsQuotes ? `"${text.replace(/"/g, '""')}"` : text;
    }

    function autocompleteEndpoint(field) {
        return field.dataset.autocompleteUrl || DEFAULT_ENDPOINTS[field.dataset.autocompleteSource] || null;
    }

    function positionDropdown(field) {
        const rect = field.getBoundingClientRect();

        dropdown.style.left = `${window.scrollX + rect.left}px`;
        dropdown.style.top = `${window.scrollY + rect.bottom + 4}px`;
        dropdown.style.width = `${rect.width}px`;
    }

    function closeDropdown(field = activeField) {
        if (!dropdown) {
            return;
        }

        dropdown.hidden = true;
        dropdown.innerHTML = '';

        if (field) {
            field.setAttribute('aria-expanded', 'false');
        }

        activeField = null;
    }

    function setCaret(field, position) {
        if (typeof field.setSelectionRange === 'function') {
            field.setSelectionRange(position, position);
        }
    }

    function dispatchFieldChange(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.addEventListener('DOMContentLoaded', () => {
        initFields();

        const observer = new MutationObserver(() => initFields());
        observer.observe(document.body, { childList: true, subtree: true });
    });

    document.addEventListener('mousedown', (event) => {
        if (activeField && event.target !== activeField && !dropdown?.contains(event.target)) {
            closeDropdown(activeField);
        }
    });

    window.addEventListener('resize', () => {
        if (activeField && !dropdown?.hidden) {
            positionDropdown(activeField);
        }
    });

    window.addEventListener('scroll', () => {
        if (activeField && !dropdown?.hidden) {
            positionDropdown(activeField);
        }
    }, true);
})();
