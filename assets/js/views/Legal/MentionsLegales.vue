<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Mentions légales
      </h1>

      <div class="prose dark:prose-invert max-w-none space-y-8">
        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. Éditeur du site
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le site MusicAll est édité par Jérémy, personne physique.
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2 mt-4">
            <li><strong>Email :</strong> <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a></li>
            <li><strong>Directeur de la publication :</strong> Jérémy</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Hébergement
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le site est hébergé par :
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2 mt-4">
            <li><strong>Scaleway SAS</strong></li>
            <li>8 rue de la Ville l'Évêque</li>
            <li>75008 Paris, France</li>
            <li><a href="https://www.scaleway.com" class="text-primary hover:text-primary-emphasis" target="_blank" rel="noopener">www.scaleway.com</a></li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Propriété intellectuelle
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            L'ensemble du contenu de ce site (textes, images, vidéos, logos, design) est protégé par le droit d'auteur. Toute reproduction, représentation, modification ou exploitation non autorisée est interdite.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Les contenus publiés par les utilisateurs restent leur propriété. En les publiant sur MusicAll, ils accordent une licence d'utilisation non exclusive pour leur affichage sur la plateforme.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Données personnelles
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Pour toute information concernant la collecte et le traitement de vos données personnelles, veuillez consulter notre
            <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">politique de confidentialité</RouterLink>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            5. Cookies
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Ce site utilise des cookies. Pour en savoir plus sur leur utilisation et gérer vos préférences, consultez notre
            <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">politique de confidentialité</RouterLink>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            6. Contact
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Pour toute question ou réclamation, vous pouvez nous contacter à l'adresse
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'

useTitle('Mentions légales - MusicAll')
</script>
