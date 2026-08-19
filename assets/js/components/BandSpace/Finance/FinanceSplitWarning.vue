<template>
  <!-- Mismatch conveyed by icon SHAPE + an accessible name, never by colour alone (WCAG 1.4.1).
       A PrimeVue tooltip carries no accessible name, so the wording is on aria-label as well as on
       the native title that shows it on hover. Amber 700 in light mode, because the rows sit on the
       grey surface band where the 500 level fails AA (same reason as the #688 darkening). -->
  <span
    role="img"
    :aria-label="SPLIT_WARNING_LABEL"
    :title="SPLIT_WARNING_LABEL"
    class="inline-flex flex-shrink-0 leading-none text-amber-700 dark:text-amber-400"
  >
    <i class="pi pi-exclamation-triangle text-xs" aria-hidden="true" />
  </span>
</template>

<script setup>
/**
 * The entry carries a répartition that no longer adds up to its amount, which the API reports as
 * split_warning and nothing on screen used to show.
 *
 * Nothing re-checks the shares when the amount changes: lowering an entry from 100 to 60 without
 * touching a 50/50 répartition writes no split at all, because both sides are unchanged. The 100
 * allocated on a 60 EUR expense is then wrong everywhere and announced nowhere, so the row needs a
 * signal of its own rather than one only the drawer's total line shows.
 *
 * Under-allocation counts too: the flag is raised as soon as the shares total anything other than
 * the amount, so the wording says "ne correspond pas" and not "dépasse".
 */
const SPLIT_WARNING_LABEL =
  'Répartition à revoir : le total réparti entre les membres ne correspond pas au montant de l’entrée.'
</script>
