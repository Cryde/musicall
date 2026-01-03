<template>
  <div class="flex flex-col gap-16 py-12 overflow-hidden">
    <!-- Hero Section -->
    <section class="text-center">
      <h1 class="text-4xl md:text-5xl font-bold text-surface-900 dark:text-surface-0 mb-4">
        Découvrez MusicAll
      </h1>
      <p class="text-lg text-surface-600 dark:text-surface-400 max-w-2xl mx-auto">
        Tout ce que vous pouvez faire sur la plateforme
      </p>
    </section>

    <!-- Publications -->
    <div class="flex xl:flex-row flex-col-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <h2 class="text-4xl font-bold text-surface-900 dark:text-surface-0 mb-4">Publications</h2>
        <p class="text-surface-600 dark:text-surface-400 text-xl leading-7">
          Découvrez des articles, tutoriels et actualités rédigés par la communauté.
          Cours de musique, techniques instrumentales, théorie musicale et bien plus encore.
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>Articles et tutoriels sur tous les instruments</FeatureItem>
          <FeatureItem>Cours de musique pour tous les niveaux</FeatureItem>
          <FeatureItem>Découvertes vidéo partagées par la communauté</FeatureItem>
          <FeatureItem>Actualités et tendances musicales</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_publications' }" class="inline-block mt-8">
          <Button label="Voir les publications" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <img
          :src="discoverPublicationImage"
          alt="Publications MusicAll"
          class="w-full max-h-96 object-contain rounded-xl"
        />
      </div>
    </div>

    <!-- Annonces musiciens -->
    <div class="flex flex-col-reverse xl:flex-row-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <h2 class="text-4xl font-bold text-surface-900 dark:text-surface-0 mb-4">Annonces musiciens</h2>
        <p class="text-surface-600 dark:text-surface-400 text-xl leading-7">
          Trouvez des musicien·ne·s pour votre groupe ou rejoignez une formation.
          Postez votre annonce et entrez en contact avec d'autres passionné·e·s.
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>Recherche de musicien·ne·s par instrument et style</FeatureItem>
          <FeatureItem>Recherche de groupes dans votre région</FeatureItem>
          <FeatureItem>Filtres par localisation géographique</FeatureItem>
          <FeatureItem>Contact direct via la messagerie intégrée</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_search_musician' }" class="inline-block mt-8">
          <Button label="Voir les annonces" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <img
          :src="discoverAnnouncesImage"
          alt="Annonces musiciens MusicAll"
          class="w-full max-h-96 object-contain rounded-xl"
        />
      </div>
    </div>

    <!-- Forum -->
    <div class="flex xl:flex-row flex-col-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <h2 class="text-4xl font-bold text-surface-900 dark:text-surface-0 mb-4">Forum</h2>
        <p class="text-surface-600 dark:text-surface-400 text-xl leading-7">
          Échangez avec la communauté sur tous les sujets liés à la musique.
          Posez vos questions, partagez vos expériences et aidez les autres.
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>Discussions thématiques par catégorie</FeatureItem>
          <FeatureItem>Conseils et entraide entre membres</FeatureItem>
          <FeatureItem>Espace de présentation pour les nouveaux</FeatureItem>
          <FeatureItem>Sujets épinglés et modération active</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_forum_index' }" class="inline-block mt-8">
          <Button label="Accéder au forum" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <img
          :src="discoverForumImage"
          alt="Forum MusicAll"
          class="w-full max-h-96 object-contain rounded-xl"
        />
      </div>
    </div>

    <!-- Galeries photos -->
    <div class="flex flex-col-reverse xl:flex-row-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <h2 class="text-4xl font-bold text-surface-900 dark:text-surface-0 mb-4">Galeries photos</h2>
        <p class="text-surface-600 dark:text-surface-400 text-xl leading-7">
          Parcourez les galeries photos de concerts et événements musicaux.
          Revivez les meilleurs moments capturés par la communauté.
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>Photos de concerts et festivals</FeatureItem>
          <FeatureItem>Événements musicaux en tout genre</FeatureItem>
          <FeatureItem>Galeries haute qualité</FeatureItem>
          <FeatureItem>Partagez vos propres photos</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_publications' }" class="inline-block mt-8">
          <Button label="Voir les galeries" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <img
          :src="discoverPhotoImage"
          alt="Galeries photos MusicAll"
          class="w-full max-h-96 object-contain rounded-xl"
        />
      </div>
    </div>

    <!-- Messagerie & Espace groupe -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <!-- Messagerie -->
      <div class="bg-surface-50 dark:bg-surface-800 rounded-2xl p-8">
        <div class="w-12 h-12 rounded-full bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center mb-4">
          <i class="pi pi-envelope text-xl text-pink-600 dark:text-pink-400" />
        </div>
        <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mb-3">Messagerie privée</h2>
        <p class="text-surface-600 dark:text-surface-400 leading-7 mb-6">
          Communiquez en privé avec les autres membres de la communauté.
          Discutez de projets, échangez des conseils ou planifiez des rencontres.
        </p>
        <div class="flex flex-col gap-3">
          <FeatureItem>Messages privés entre membres</FeatureItem>
          <FeatureItem>Notifications en temps réel</FeatureItem>
          <FeatureItem>Contact direct depuis les annonces</FeatureItem>
        </div>
      </div>

      <!-- Espace groupe (Coming Soon) -->
      <div class="bg-surface-50 dark:bg-surface-800 rounded-2xl p-8 opacity-75">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
            <i class="pi pi-users text-xl text-purple-600 dark:text-purple-400" />
          </div>
          <span class="bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 text-sm font-medium px-3 py-1 rounded-full">
            Bientôt
          </span>
        </div>
        <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mb-3">Espace groupe</h2>
        <p class="text-surface-600 dark:text-surface-400 leading-7 mb-6">
          Gérez votre groupe de musique avec des outils collaboratifs.
          Organisez vos répétitions et communiquez efficacement.
        </p>
        <p class="text-surface-500 dark:text-surface-400 italic">
          Cette fonctionnalité sera disponible prochainement.
        </p>
      </div>
    </div>

    <!-- CTA Section -->
    <section v-if="!userSecurityStore.isAuthenticated" class="text-center py-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-2xl">
      <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">
        Prêt·e à rejoindre la communauté ?
      </h2>
      <p class="text-primary-100 mb-6">
        Créez votre compte gratuitement et commencez à échanger avec des milliers de passionné·e·s
      </p>
      <router-link :to="{ name: 'app_register' }">
        <Button label="Créer mon compte" icon="pi pi-user-plus" size="large" severity="contrast" />
      </router-link>
    </section>

    <!-- Back to home -->
    <div class="text-center">
      <router-link :to="{ name: 'app_home' }">
        <Button label="Retour à l'accueil" icon="pi pi-arrow-left" severity="secondary" text />
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import { useUserSecurityStore } from '../../store/user/security.js'
import FeatureItem from '../../components/Discover/FeatureItem.vue'
import discoverPublicationImage from '../../../image/discover/discover-publication.png'
import discoverAnnouncesImage from '../../../image/discover/discover-announces.png'
import discoverPhotoImage from '../../../image/discover/discover-photo.png'
import discoverForumImage from '../../../image/discover/discover-forum.png'

useTitle('Découvrir MusicAll - La communauté de passionné·e·s de musique')

const userSecurityStore = useUserSecurityStore()
</script>
