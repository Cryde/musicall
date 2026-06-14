<template>
  <!-- Visually hidden until focused; first focusable element so keyboard/SR users
       can bypass the nav and jump straight to <main id="main-content">. -->
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded focus:bg-primary focus:px-4 focus:py-2 focus:text-white focus:shadow-lg"
  >
    Aller au contenu
  </a>
</template>
