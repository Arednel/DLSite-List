document.addEventListener('alpine:init', () => {
    Alpine.data('tagLibraryFilters', () => ({
        filtersOpen: false,

        init() {
            this.$watch('filtersOpen', (isOpen) => {
                document.body.classList.toggle('filter-modal-open', isOpen);
            });
        },

        openFilters() {
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

        destroy() {
            document.body.classList.remove('filter-modal-open');
        },
    }));
});
