<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Legal notice
      </h1>

      <p class="text-surface-600 dark:text-surface-300 mb-6">
        Last updated: {{ lastUpdate }}
      </p>

      <div class="prose dark:prose-invert max-w-none space-y-8">
        <!-- No postal address on purpose, see the comment in ../MentionsLegales.vue. -->
        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. Publisher of the site
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            The MusicAll site is published by Jérémy Tonneau, a natural person.
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2 mt-4">
            <li><strong>Contact:</strong> <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a></li>
            <li><strong>Director of publication:</strong> Jérémy Tonneau</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            MusicAll is a personal, non-profit project. The site generates no revenue: no advertising, no affiliate links, no paid services.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Hosting
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Application servers and file storage:
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2 mt-4">
            <li><strong>Scaleway SAS</strong></li>
            <li>8 rue de la Ville l'Évêque</li>
            <li>75008 Paris, France</li>
            <li><a href="https://www.scaleway.com" class="text-primary hover:text-primary-emphasis" target="_blank" rel="noopener">www.scaleway.com</a></li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            The servers and the object storage (fr-par region, Paris) are located in the European Union.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Intellectual property
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            The MusicAll site, its design, its source code, its logos and its editorial content are protected by copyright. Any unauthorised reproduction, communication to the public, modification or exploitation is prohibited.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Content published by users remains their property. By publishing it on MusicAll, they grant a non-exclusive, free licence to display and distribute it as part of the operation of the platform.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Personal data
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            For any information about the collection and processing of your personal data, see our
            <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">privacy policy</RouterLink>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            5. Cookies and audience measurement
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            The site only sets the cookies it needs to work:
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>jwt_hp</strong> and <strong>jwt_s</strong>: keep you logged in (1 hour)</li>
            <li><strong>refresh_token</strong>: renews your session without asking for your password again (30 days)</li>
            <li><strong>__Secure-mercure_access_token</strong>: receives notifications in real time (1 hour)</li>
            <li><strong>PHPSESSID</strong>: technical server session (for the length of your visit)</li>
            <li><strong>is_dark_mode</strong>: remembers the light or dark theme you chose (1 year)</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            MusicAll sets no advertising, profiling or cross-site tracking cookies. There is no advertising, no ad network and no conversion tracking on the site.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Audience measurement is provided by Umami, a self-hosted tool that sets no cookies and produces aggregated statistics.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            However, some pages show content provided by external platforms: YouTube videos, Spotify and SoundCloud players, and Google Fonts. When this content is loaded, your browser communicates directly with these platforms, which may set their own cookies. The details are in our
            <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">privacy policy</RouterLink>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            6. Reporting illegal content
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            In accordance with Regulation (EU) 2022/2065 on digital services (the Digital Services Act), any user or entity can report content they consider illegal by writing to
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Please give the address (URL) of the content concerned, the nature of the problem and the reasons for your report. Each report is acknowledged, examined and answered with a reasoned decision sent to the person who made it.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            7. Governing law
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            The site is published and operated from Belgium. It is subject to Belgian law.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            8. Contact
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            For any question or complaint:
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
// The English version of ../MentionsLegales.vue: change both together, i18n/legalPages.test.js
// checks that they keep the same markup.
import { useTitle } from '@vueuse/core'

useTitle('Legal notice - MusicAll')

const lastUpdate = '29 July 2026'
</script>
