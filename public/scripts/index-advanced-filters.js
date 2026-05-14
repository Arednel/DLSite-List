document.addEventListener('alpine:init', () => {
    Alpine.data('indexAdvancedFilters', () => ({
        filtersOpen: false,
        primarySort: '',
        secondarySort: '',

        init() {
            this.syncSortStateFromControls();

            this.$watch('filtersOpen', (isOpen) => {
                document.body.classList.toggle('filter-modal-open', isOpen);
            });
        },

        openFilters() {
            this.syncSortStateFromControls();
            this.filtersOpen = true;
        },

        closeFilters() {
            this.filtersOpen = false;
        },

        syncSortStateFromControls() {
            this.primarySort = this.$refs.primarySortSelect?.value || '';
            this.secondarySort = this.$refs.secondarySortSelect?.value || '';
        },

        setPrimarySort(value) {
            this.primarySort = value || '';
            this.clearSecondarySortWhenUnavailable();
        },

        setSecondarySort(value) {
            this.secondarySort = value || '';
        },

        clearSecondarySortWhenUnavailable() {
            if (this.primarySort !== '' || !this.$refs.secondarySortSelect) {
                return;
            }

            this.secondarySort = '';
            this.$refs.secondarySortSelect.value = '';
            this.$refs.secondarySortSelect.dispatchEvent(new Event('change', { bubbles: true }));
        },

        destroy() {
            document.body.classList.remove('filter-modal-open');
        },
    }));
});
