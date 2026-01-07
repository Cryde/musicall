<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Politique de confidentialité
      </h1>

      <p class="text-surface-600 dark:text-surface-300 mb-6">
        Dernière mise à jour : {{ lastUpdate }}
      </p>

      <div class="prose dark:prose-invert max-w-none space-y-8">
        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. Responsable du traitement
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le site MusicAll est édité par Jérémy. Pour toute question relative à la protection de vos données personnelles, vous pouvez nous contacter à l'adresse :
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Données collectées
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Nous collectons les données suivantes :
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Données de compte</strong> : adresse email, nom d'utilisateur</li>
            <li><strong>Données de profil</strong> : photo de profil (optionnelle), informations musicales que vous choisissez de partager</li>
            <li><strong>Données de connexion</strong> : identifiants OAuth (Google, Facebook) si vous utilisez ces méthodes de connexion</li>
            <li><strong>Données de navigation</strong> : cookies, statistiques de visite (via Google Analytics)</li>
            <li><strong>Données techniques</strong> : informations sur les erreurs pour améliorer le service (via Sentry)</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Finalités du traitement
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Vos données sont utilisées pour :
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>Créer et gérer votre compte utilisateur</li>
            <li>Vous permettre de vous connecter via Google ou Facebook</li>
            <li>Vous permettre de publier du contenu et d'interagir avec la communauté</li>
            <li>Analyser l'utilisation du site pour l'améliorer</li>
            <li>Détecter et corriger les erreurs techniques</li>
            <li>Vous envoyer des notifications liées à votre compte (si vous l'avez autorisé)</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Base légale
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le traitement de vos données repose sur votre consentement (lors de l'inscription et de l'acceptation des cookies) et sur l'exécution du contrat (fourniture du service MusicAll).
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            5. Services tiers
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Nous utilisons les services tiers suivants :
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Google OAuth / Facebook Login</strong> : pour vous permettre de vous connecter avec vos comptes existants</li>
            <li><strong>Google Analytics</strong> : pour analyser la fréquentation du site (données anonymisées)</li>
            <li><strong>Sentry</strong> : pour détecter et corriger les erreurs techniques</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Ces services peuvent collecter des données conformément à leurs propres politiques de confidentialité.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            6. Cookies
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Nous utilisons des cookies pour :
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Cookies essentiels</strong> : maintenir votre session de connexion</li>
            <li><strong>Cookies analytiques</strong> : mesurer l'audience du site (Google Analytics)</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Vous pouvez gérer vos préférences de cookies dans les paramètres de votre navigateur.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            7. Durée de conservation
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Vos données sont conservées tant que votre compte est actif. En cas de suppression de compte, vos données personnelles sont supprimées dans un délai de 30 jours, à l'exception des données que nous sommes légalement tenus de conserver.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            8. Vos droits
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Conformément au RGPD, vous disposez des droits suivants :
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Droit d'accès</strong> : obtenir une copie de vos données personnelles</li>
            <li><strong>Droit de rectification</strong> : corriger vos données inexactes</li>
            <li><strong>Droit à l'effacement</strong> : demander la suppression de vos données</li>
            <li><strong>Droit à la portabilité</strong> : recevoir vos données dans un format structuré</li>
            <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données</li>
            <li><strong>Droit de retirer votre consentement</strong> : à tout moment</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Pour exercer ces droits, contactez-nous à
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            9. Sécurité
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Nous mettons en place des mesures techniques et organisationnelles appropriées pour protéger vos données personnelles contre tout accès non autorisé, modification, divulgation ou destruction.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            10. Contact
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Pour toute question concernant cette politique de confidentialité ou vos données personnelles, contactez-nous à
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Vous avez également le droit d'introduire une réclamation auprès de la CNIL (Commission Nationale de l'Informatique et des Libertés) si vous estimez que le traitement de vos données n'est pas conforme à la réglementation.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'

useTitle('Politique de confidentialité - MusicAll')

const lastUpdate = '7 janvier 2026'
</script>
