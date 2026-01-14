<template>
  <div class="flex flex-col gap-20 py-12 overflow-hidden">
    <!-- Hero Section -->
    <section class="text-center relative">
      <!-- Decorative background elements -->
      <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary-200/30 dark:bg-primary-500/10 rounded-full blur-3xl" />
        <div class="absolute -top-12 -right-12 w-72 h-72 bg-pink-200/30 dark:bg-pink-500/10 rounded-full blur-3xl" />
      </div>

      <div class="inline-flex items-center gap-2 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-4 py-2 rounded-full text-sm font-medium mb-6">
        <i class="pi pi-sparkles" />
        <span>La plateforme des passionné·e·s de musique</span>
      </div>

      <h1 class="text-4xl md:text-6xl font-extrabold mb-6">
        <span class="text-surface-900 dark:text-surface-0">Découvrez </span>
        <span class="bg-gradient-to-r from-primary-500 via-pink-500 to-purple-500 bg-clip-text text-transparent">MusicAll</span>
      </h1>

      <p class="text-xl text-surface-600 dark:text-surface-400 max-w-2xl mx-auto leading-relaxed">
        Explorez toutes les fonctionnalités qui font de MusicAll
        <span class="font-semibold text-surface-700 dark:text-surface-300">votre communauté musicale</span>
      </p>
    </section>

    <!-- Publications -->
    <div class="flex xl:flex-row flex-col-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
            <i class="pi pi-book text-2xl text-blue-600 dark:text-blue-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">Publications</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
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
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <img
            :src="discoverPublicationImage"
            alt="Publications MusicAll"
            class="w-full max-h-96 object-contain rounded-2xl"
          />
        </div>
      </div>
    </div>

    <!-- Annonces musiciens -->
    <div class="flex flex-col-reverse xl:flex-row-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
            <i class="pi pi-megaphone text-2xl text-orange-600 dark:text-orange-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">Annonces musiciens</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
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
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-orange-500/20 to-red-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <img
            :src="discoverAnnouncesImage"
            alt="Annonces musiciens MusicAll"
            class="w-full max-h-96 object-contain rounded-2xl"
          />
        </div>
      </div>
    </div>

    <!-- Forum -->
    <div class="flex xl:flex-row flex-col-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
            <i class="pi pi-comments text-2xl text-green-600 dark:text-green-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">Forum</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
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
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-green-500/20 to-teal-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <img
            :src="discoverForumImage"
            alt="Forum MusicAll"
            class="w-full max-h-96 object-contain rounded-2xl"
          />
        </div>
      </div>
    </div>

    <!-- Galeries photos -->
    <div class="flex flex-col-reverse xl:flex-row-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
            <i class="pi pi-images text-2xl text-violet-600 dark:text-violet-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">Galeries photos</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
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
        <router-link :to="{ name: 'app_publications_by_category', params: { slug: 'photos' } }" class="inline-block mt-8">
          <Button label="Voir les galeries" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-violet-500/20 to-pink-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <img
            :src="discoverPhotoImage"
            alt="Galeries photos MusicAll"
            class="w-full max-h-96 object-contain rounded-2xl"
          />
        </div>
      </div>
    </div>

    <!-- Messagerie & Espace groupe -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <!-- Messagerie -->
      <div class="group relative bg-surface-50 dark:bg-surface-800 rounded-2xl p-8 border border-surface-200 dark:border-surface-700 transition-all duration-300 hover:border-pink-300 dark:hover:border-pink-700 hover:shadow-lg hover:shadow-pink-500/10">
        <div class="absolute inset-0 bg-gradient-to-br from-pink-500/5 to-transparent rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        <div class="relative">
          <div class="w-14 h-14 rounded-xl bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center mb-5">
            <i class="pi pi-envelope text-2xl text-pink-600 dark:text-pink-400" />
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
      </div>

      <!-- Espace groupe (Coming Soon) -->
      <div class="relative bg-surface-50 dark:bg-surface-800 rounded-2xl p-8 border border-dashed border-surface-300 dark:border-surface-600">
        <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent rounded-2xl" />
        <div class="relative">
          <div class="flex items-center gap-3 mb-5">
            <div class="w-14 h-14 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
              <i class="pi pi-users text-2xl text-purple-600 dark:text-purple-400" />
            </div>
            <span class="bg-gradient-to-r from-purple-500 to-pink-500 text-white text-sm font-medium px-4 py-1.5 rounded-full shadow-sm">
              Bientôt disponible
            </span>
          </div>
          <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mb-3">Espace groupe</h2>
          <p class="text-surface-600 dark:text-surface-400 leading-7 mb-6">
            Gérez votre groupe de musique avec des outils collaboratifs.
            Organisez vos répétitions et communiquez efficacement.
          </p>
          <div class="flex flex-col gap-3">
            <FeatureItem soon>Gestion des membres du groupe</FeatureItem>
            <FeatureItem soon>Calendrier partagé des répétitions</FeatureItem>
            <FeatureItem soon>Espace de stockage pour vos morceaux</FeatureItem>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <section v-if="!userSecurityStore.isAuthenticated" class="relative text-center py-16 px-8 rounded-3xl overflow-hidden">
      <!-- Gradient background -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-500 via-pink-500 to-purple-600" />

      <!-- Decorative circles -->
      <div class="absolute top-0 left-0 w-64 h-64 bg-white/10 rounded-full -translate-x-1/2 -translate-y-1/2" />
      <div class="absolute bottom-0 right-0 w-48 h-48 bg-white/10 rounded-full translate-x-1/3 translate-y-1/3" />

      <div class="relative">
        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
          <i class="pi pi-heart-fill" />
          <span>Rejoignez une communauté active</span>
        </div>

        <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">
          Prêt·e à rejoindre l'aventure ?
        </h2>
        <p class="text-white/90 text-lg mb-8 max-w-xl mx-auto">
          Créez votre compte gratuitement et commencez à échanger avec des milliers de passionné·e·s de musique
        </p>
        <router-link :to="{ name: 'app_register' }">
          <Button label="Créer mon compte gratuitement" icon="pi pi-user-plus" size="large" severity="contrast" class="shadow-xl" />
        </router-link>
      </div>
    </section>

    <!-- Back to home -->
    <div class="text-center pb-8">
      <router-link :to="{ name: 'app_home' }">
        <Button label="Retour à l'accueil" icon="pi pi-arrow-left" severity="secondary" outlined />
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import discoverAnnouncesImage from '../../../image/discover/discover-announces.png'
import discoverForumImage from '../../../image/discover/discover-forum.png'
import discoverPhotoImage from '../../../image/discover/discover-photo.png'
import discoverPublicationImage from '../../../image/discover/discover-publication.png'
import FeatureItem from '../../components/Discover/FeatureItem.vue'
import { useUserSecurityStore } from '../../store/user/security.js'

useTitle('Découvrir MusicAll - La communauté de passionné·e·s de musique')

const userSecurityStore = useUserSecurityStore()
</script>
