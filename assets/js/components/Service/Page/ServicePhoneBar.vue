<template>
  <!-- Phone: the price and the main action stay at hand -->
  <div
    class="md:hidden fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] bg-surface-0/95 dark:bg-surface-900/95 backdrop-blur border-t border-surface-200 dark:border-surface-700"
  >
    <slot />
  </div>
</template>
