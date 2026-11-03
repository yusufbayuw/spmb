{{-- Filament 3 table enhancement scoped to the registration identity column.
     No table templates, Alpine state, payment logic, or vendor assets are modified. --}}
<style>
    @media (min-width: 1024px) {
        .fi-ta-table:has(.spmb-registration-sticky-identity) .spmb-registration-sticky-identity {
            position: sticky;
            left: 0;
            z-index: 2;
            min-width: 15rem;
            background-color: rgb(255 255 255);
            box-shadow: 3px 0 6px -5px rgb(15 23 42 / 0.35);
        }

        .dark .fi-ta-table:has(.spmb-registration-sticky-identity) .spmb-registration-sticky-identity {
            background-color: rgb(17 24 39);
        }

        /* Bulk selection checkbox precedes the first data column in Filament 3. */
        .fi-ta-table:has(.spmb-registration-sticky-identity) .fi-ta-selection-cell:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            width: 3.5rem;
            min-width: 3.5rem;
            background-color: rgb(255 255 255);
        }

        .dark .fi-ta-table:has(.spmb-registration-sticky-identity) .fi-ta-selection-cell:first-child {
            background-color: rgb(17 24 39);
        }

        .fi-ta-table:has(.fi-ta-selection-cell:first-child) .spmb-registration-sticky-identity {
            left: 3.5rem;
        }

        /* The header needs to stay above the sticky body cells. */
        .fi-ta-table:has(.spmb-registration-sticky-identity) thead .spmb-registration-sticky-identity,
        .fi-ta-table:has(.spmb-registration-sticky-identity) thead .fi-ta-selection-cell:first-child {
            z-index: 3;
            background-color: rgb(249 250 251);
        }

        .dark .fi-ta-table:has(.spmb-registration-sticky-identity) thead .spmb-registration-sticky-identity,
        .dark .fi-ta-table:has(.spmb-registration-sticky-identity) thead .fi-ta-selection-cell:first-child {
            background-color: rgb(17 24 39);
        }
    }
</style>
