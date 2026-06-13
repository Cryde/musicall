<template>
  <transition-group name="fade" appear>
    <slot />
  </transition-group>
</template>

<script setup>
// Reusable fade-in for async lists: once the skeleton is replaced by real data,
// the items fade in smoothly (and any appended/swapped items animate the same way).
// Slotted children MUST be keyed — transition-group requires it.
</script>

<!--
  Unscoped on purpose: transition-group applies its `.fade-*` classes to the
  slotted children, which belong to the PARENT component's scope. Scoped styles
  here would never reach them, so the transition must be declared globally.
  `fade` is only used through this component, so there is no collision.
-->
<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}
</style>
