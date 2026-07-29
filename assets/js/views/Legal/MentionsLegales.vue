<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Mentions légales
      </h1>

      <p class="text-surface-600 dark:text-surface-300 mb-6">
        Dernière mise à jour : {{ lastUpdate }}
      </p>

      <div class="prose dark:prose-invert max-w-none space-y-8">
        <!--
          No postal address is published because the site currently has zero economic activity,
          which is what triggers the CDE art. III.74 / XII.6 obligation. A geographic address
          becomes mandatory as soon as any revenue exists (ads, affiliation, paid storage) or as
          soon as the project is moved into a legal entity (ASBL, indépendant complémentaire,
          société).
        -->
        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. Éditeur du site
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le site MusicAll est édité par Jérémy Tonneau, personne physique.
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2 mt-4">
            <li><strong>Contact :</strong> <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a></li>
            <li><strong>Directeur de la publication :</strong> Jérémy Tonneau</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            MusicAll est un projet personnel à but non lucratif. Le site ne génère aucun revenu : ni publicité, ni affiliation, ni service payant.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Hébergement
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Serveurs applicatifs et stockage des fichiers :
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2 mt-4">
            <li><strong>Scaleway SAS</strong></li>
            <li>8 rue de la Ville l'Évêque</li>
            <li>75008 Paris, France</li>
            <li><a href="https://www.scaleway.com" class="text-primary hover:text-primary-emphasis" target="_blank" rel="noopener">www.scaleway.com</a></li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Les serveurs et le stockage objet (région fr-par, Paris) sont situés dans l'Union européenne.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Propriété intellectuelle
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le site MusicAll, son design, son code source, ses logos et son contenu éditorial sont protégés par le droit d'auteur. Toute reproduction, représentation, modification ou exploitation non autorisée est interdite.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Les contenus publiés par les utilisateurs restent leur propriété. En les publiant sur MusicAll, ils accordent une licence non exclusive et gratuite permettant leur affichage et leur diffusion dans le cadre du fonctionnement de la plateforme.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Données personnelles
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Pour toute information concernant la collecte et le traitement de vos données personnelles, consultez notre
            <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">politique de confidentialité</RouterLink>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            5. Cookies et mesure d'audience
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Le site dépose uniquement les cookies nécessaires à son fonctionnement :
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>jwt_hp</strong> et <strong>jwt_s</strong> : maintien de votre session de connexion (1 heure)</li>
            <li><strong>refresh_token</strong> : renouvellement de votre session sans nouvelle saisie de mot de passe (30 jours)</li>
            <li><strong>PHPSESSID</strong> : session technique du serveur (durée de votre navigation)</li>
            <li><strong>is_dark_mode</strong> : mémorisation du thème clair ou sombre que vous avez choisi (1 an)</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Aucun cookie publicitaire, de profilage ou de suivi entre sites n'est déposé par MusicAll. Il n'y a sur le site ni publicité, ni régie, ni suivi de conversion.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            La mesure d'audience est assurée par Umami, une solution auto-hébergée qui ne dépose pas de cookie et produit des statistiques agrégées.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            En revanche, certaines pages affichent des contenus fournis par des plateformes externes : vidéos YouTube, lecteurs Spotify et SoundCloud, et polices de caractères Google Fonts. Lorsque ces contenus sont chargés, votre navigateur communique directement avec ces plateformes, qui peuvent déposer leurs propres cookies. Le détail figure dans notre
            <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">politique de confidentialité</RouterLink>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            6. Signalement d'un contenu illicite
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Conformément au règlement (UE) 2022/2065 sur les services numériques, tout utilisateur ou toute entité peut signaler un contenu qu'il estime illicite en écrivant à
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Merci d'indiquer l'adresse (URL) du contenu concerné, la nature du problème et les motifs de votre signalement. Chaque signalement fait l'objet d'un accusé de réception, d'un examen, et d'une décision motivée communiquée à son auteur.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            7. Droit applicable
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Le site est édité et exploité depuis la Belgique. Il est soumis au droit belge.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            8. Contact
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Pour toute question ou réclamation :
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

const lastUpdate = '29 juillet 2026'
</script>
