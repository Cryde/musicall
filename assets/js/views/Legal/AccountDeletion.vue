<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Supprimer votre compte MusicAll
      </h1>

      <p class="text-surface-600 dark:text-surface-300 mb-6">
        Dernière mise à jour : {{ lastUpdate }}
      </p>

      <!--
        The page Google Play's Data safety form points to as the "Delete account URL" (#1130), so it
        must stay public and at this address. What it says is deleted, kept and for how long has to
        agree with DeleteAccountProcedure, Terms §4, §5.4 and §13, and Privacy §4, §7 and §8.
      -->
      <div class="prose dark:prose-invert max-w-none space-y-8">
        <section>
          <p class="text-surface-600 dark:text-surface-300">
            Cette page explique comment supprimer votre compte MusicAll, que vous l'utilisiez depuis l'application
            mobile MusicAll ou depuis le site www.musicall.com. L'application et le site sont édités par Jérémy
            Tonneau et partagent le même compte : le supprimer depuis l'un le supprime partout.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. Comment supprimer votre compte
          </h2>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>
              <strong>Dans l'application</strong> : ouvrez <strong>Profil</strong>, puis <strong>Réglages</strong>, et
              touchez « Supprimer mon compte ».
            </li>
            <li>
              <strong>Sur le site</strong> : connectez-vous, ouvrez vos
              <router-link :to="{ name: 'app_user_settings' }" class="text-primary hover:text-primary-emphasis">paramètres</router-link>
              et cliquez sur « Supprimer mon compte », dans la zone de danger.
            </li>
            <li>
              <strong>Si vous ne pouvez plus vous connecter</strong> : écrivez à
              <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>
              depuis l'adresse email de votre compte, en indiquant votre nom d'utilisateur. Nous pourrons vous demander de
              confirmer que le compte vous appartient, et nous traitons la demande dans un délai d'un mois.
            </li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            La suppression est immédiate et irréversible. Si vous êtes membre d'un Band Space, pensez à récupérer avant
            les fichiers que vous souhaitez garder.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Ce qui est supprimé
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Dès que vous confirmez, vos données personnelles sont supprimées ou anonymisées :
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>nom d'utilisateur, adresse email, mot de passe et compte Google associé ;</li>
            <li>photo de profil, image de couverture, nom affiché, biographie, localisation et liens vers vos réseaux ;</li>
            <li>vos fiches musicien et professeur, qui ne sont plus accessibles, et vos annonces, qui ne sont plus affichées ;</li>
            <li>vos préférences de notification et les utilisateurs que vous aviez bloqués ;</li>
            <li>vos sessions : vous êtes déconnecté de tous vos appareils, et votre téléphone ne reçoit plus de notifications push.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Ce qui est conservé
          </h2>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>
              Vos contenus publics (publications, cours, galeries, commentaires, messages du forum) et les messages privés
              que vous avez envoyés restent visibles, rattachés à un profil anonyme affiché « Utilisateur supprimé ».
            </li>
            <li>
              Vous quittez chacun de vos Band Spaces. Les contenus que vous y avez déposés restent à la disposition du
              groupe, rattachés au même profil anonyme. Si vous en étiez le seul administrateur, le membre le plus ancien
              le devient. Si vous en étiez le seul membre, l'espace est programmé pour suppression et effacé définitivement,
              avec tous ses contenus, au bout de 30 jours.
            </li>
            <li>
              L'enregistrement anonymisé de votre compte est conservé, sans aucune donnée permettant de vous identifier,
              afin que les contenus publics et les espaces de groupe restent cohérents.
            </li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Pendant combien de temps
          </h2>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Données de votre compte :</strong> supprimées ou anonymisées immédiatement</li>
            <li><strong>Band Space dont vous étiez le seul membre :</strong> 30 jours, puis effacement définitif</li>
            <li><strong>Sauvegardes chiffrées :</strong> vos données peuvent y figurer jusqu'à 1 an, avant d'être écrasées</li>
            <li><strong>Journaux du serveur :</strong> 52 jours pour les journaux d'accès, 90 jours au maximum pour les journaux système, 10 jours pour les journaux applicatifs</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Le détail des traitements et de vos droits figure dans la
            <router-link :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">politique de confidentialité</router-link>
            et dans les
            <router-link :to="{ name: 'app_terms' }" class="text-primary hover:text-primary-emphasis">conditions générales d'utilisation</router-link>.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'

useTitle('Supprimer votre compte - MusicAll')

const lastUpdate = '7 octobre 2026'
</script>
