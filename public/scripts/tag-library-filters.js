document.addEventListener('alpine:init', () => {
    Alpine.data('tagLibraryFilters', () => ({
        filtersOpen: false,
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
            this.$nextTick(() => this.$refs.firstFilterControl?.focus());
        },

        closeFilters() {
            if (!this.filtersOpen) {
                return;
            }

            this.filtersOpen = false;
            this.$nextTick(() => this.$refs.filterButton?.focus());
        },

        syncSortStateFromControls() {
            this.secondarySort = this.$refs.secondarySortSelect?.value || '';
        },

        setSecondarySort(value) {
            this.secondarySort = value || '';
        },

        destroy() {
            document.body.classList.remove('filter-modal-open');
        },
    }));
});
