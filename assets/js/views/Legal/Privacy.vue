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
            Le responsable du traitement de vos données personnelles est Jérémy Tonneau, éditeur du site MusicAll.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Contact : <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Aucun délégué à la protection des données n'a été désigné, cette désignation n'étant pas requise au vu de la nature et du volume des traitements réalisés.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Données que nous traitons
          </h2>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Compte</strong> : adresse email, nom d'utilisateur, mot de passe (stocké sous forme hachée)</li>
            <li><strong>Connexion via Google</strong> (si vous choisissez ce mode de connexion) : identifiant de compte Google et adresse email associée</li>
            <li><strong>Profil</strong> : photo de profil, image de couverture, biographie, instruments, styles musicaux, localisation, liens vers vos réseaux, fiches annuaire musicien et professeur</li>
            <li><strong>Contenus publics</strong> : publications, cours, galeries, commentaires, messages du forum, annonces, votes et compteurs de vues</li>
            <li><strong>Messages privés</strong> échangés avec d'autres utilisateurs</li>
            <li><strong>Band Space</strong> : notes, tâches et leurs commentaires, entrées d'agenda, setlists, fichiers déposés, entrées financières (libellés, montants, catégories, échéances, répartitions), invitations, appartenances et journal d'activité de l'espace</li>
            <li><strong>Notifications</strong> et préférences d'envoi d'emails</li>
            <li><strong>Recherche assistée de musiciens</strong> : le texte libre que vous saisissez dans cette recherche</li>
            <li><strong>Données techniques</strong> : adresse IP, journaux du serveur, rapports d'erreur applicative</li>
            <li><strong>Mesure d'audience</strong> : statistiques de visite agrégées, sans cookie</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Votre adresse IP sert à sécuriser le service et à limiter le débit des requêtes, et figure dans les journaux du serveur. Elle n'est pas conservée en base de données : les votes et les compteurs de vues reposent sur un identifiant haché qui ne permet pas de remonter à votre adresse.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Finalités et bases légales
          </h2>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li>Créer et gérer votre compte, vous permettre d'utiliser le site et Band Space : <strong>exécution du contrat</strong> (art. 6.1.b RGPD)</li>
            <li>Vous permettre de vous connecter avec votre compte Google : <strong>exécution du contrat</strong></li>
            <li>Sécuriser le service, prévenir les abus et le spam, limiter le débit des requêtes : <strong>intérêt légitime</strong> (art. 6.1.f)</li>
            <li>Détecter et corriger les erreurs techniques : <strong>intérêt légitime</strong></li>
            <li>Mesurer l'audience du site de façon agrégée afin de l'améliorer : <strong>intérêt légitime</strong></li>
            <li>Interpréter votre recherche de musiciens en langage naturel pour la convertir en filtres : <strong>intérêt légitime</strong></li>
            <li>Vous envoyer les emails nécessaires au fonctionnement de votre compte (validation d'adresse, réinitialisation de mot de passe, invitations) : <strong>exécution du contrat</strong></li>
            <li>Vous envoyer les emails de suivi de votre activité (réponses, commentaires, messages reçus, récapitulatifs, actualités du site), que vous pouvez désactiver à tout moment depuis vos paramètres : <strong>intérêt légitime</strong></li>
            <li>Vous envoyer des emails à caractère promotionnel : <strong>consentement</strong> (art. 6.1.a), désactivé par défaut et retirable à tout moment</li>
            <li>Traiter les signalements de contenu et documenter les décisions de modération : <strong>obligation légale</strong></li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Band Space, espace partagé
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Un Band Space est un espace de travail privé, partagé entre les membres d'un même groupe.
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>Tout contenu déposé dans un Band Space est accessible à l'ensemble des membres de cet espace. En rejoignant un espace, vous acceptez cette visibilité.</li>
            <li>L'administrateur de l'espace a accès à l'ensemble des contenus et gère les membres et les invitations.</li>
            <li>Les entrées financières marquées comme personnelles sont visibles par tous les membres de l'espace. Elles sont seulement exclues des totaux et des graphiques du groupe, et ne peuvent être modifiées que par leur auteur. Ne saisissez donc pas dans ce module une information que vous ne souhaitez pas partager avec les autres membres.</li>
            <li>Les données déposées dans un Band Space ne sont ni revendues, ni exploitées à des fins publicitaires, ni utilisées pour du profilage. Elles ne sont consultées par l'éditeur que lorsque c'est strictement nécessaire au fonctionnement ou à la sécurité du service, ou pour répondre à une obligation légale.</li>
            <li>Lorsque vous quittez un espace, les contenus que vous y avez déposés y restent, afin que le travail du groupe reste cohérent. Vos entrées financières personnelles récurrentes sont désactivées et leurs échéances futures encore prévues sont supprimées.</li>
            <li>Lorsque vous supprimez votre compte, vos contenus Band Space restent également en place et votre appartenance à l'espace est conservée, mais votre nom d'utilisateur est remplacé par un identifiant anonyme.</li>
            <li>Un administrateur peut supprimer un Band Space. La suppression n'est pas immédiate : l'espace reste accessible pendant 30 jours, afin que chaque membre puisse récupérer ses fichiers, et un administrateur peut l'annuler pendant ce délai. Passé les 30 jours, l'espace et l'ensemble de ses contenus, fichiers compris, sont définitivement supprimés. Les membres sont informés dès qu'une suppression est programmée ou annulée.</li>
            <li>Un fichier supprimé depuis un Band Space est immédiatement retiré de l'espace et n'est plus accessible à ses membres. Il est définitivement effacé de nos serveurs 30 jours plus tard.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            5. Destinataires et sous-traitants
          </h2>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Scaleway SAS</strong> : hébergement des serveurs et stockage des fichiers, France (UE)</li>
            <li><strong>Amazon Web Services</strong> : stockage des sauvegardes chiffrées, région Europe (Paris), France (UE)</li>
            <li><strong>Brevo</strong> : envoi des emails transactionnels, France (UE)</li>
            <li><strong>Sentry</strong> : détection des erreurs applicatives</li>
            <li><strong>Google Ireland Limited</strong> : connexion via compte Google, si vous l'utilisez, Irlande (UE)</li>
            <li><strong>OpenAI</strong> : interprétation du texte libre de la recherche assistée de musiciens, États-Unis</li>
            <li><strong>Komoot GmbH</strong> : suggestion de villes lorsque vous saisissez une localisation, Allemagne (UE)</li>
            <li><strong>Google / YouTube</strong>, <strong>Spotify</strong> et <strong>SoundCloud</strong> : lecture des vidéos et des morceaux intégrés dans certaines pages</li>
            <li><strong>Google Fonts</strong> : fourniture des polices de caractères du site</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Les lecteurs Spotify et SoundCloud, ainsi que les vidéos YouTube affichées sur les profils de musiciens et de professeurs, ne sont chargés qu'au moment où vous cliquez pour les lancer. En revanche, les vidéos des publications de type vidéo et celles insérées dans le corps d'un article sont chargées dès l'ouverture de la page, comme les polices Google Fonts : votre navigateur transmet alors votre adresse IP à ces plateformes sans action de votre part.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Aucune donnée personnelle n'est vendue, louée ou cédée à des tiers à des fins publicitaires ou commerciales.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            6. Transferts en dehors de l'Union européenne
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Vos données de compte, vos contenus et vos fichiers sont hébergés et sauvegardés dans l'Union européenne : les serveurs et les fichiers chez Scaleway en France, les sauvegardes chez Amazon Web Services en région Europe (Paris). Les sauvegardes sont chiffrées avant leur transmission : l'hébergeur n'a accès qu'à des données illisibles.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Deux traitements sortent de ce cadre. Le texte que vous saisissez dans la recherche assistée de musiciens est transmis à OpenAI, dont les serveurs sont situés aux États-Unis, afin d'être converti en filtres de recherche. Et lorsqu'une vidéo, un lecteur audio ou une police de caractères externe est chargé, votre navigateur communique directement avec Google, Spotify ou SoundCloud, dont les serveurs peuvent être situés en dehors de l'Union européenne.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            7. Durées de conservation
          </h2>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Compte et contenus associés :</strong> conservés tant que votre compte est actif</li>
            <li><strong>Après suppression de votre compte :</strong> vos données personnelles sont anonymisées immédiatement, et l'enregistrement anonymisé est conservé afin de préserver la cohérence des contenus publics et des espaces de groupe</li>
            <li><strong>Journaux d'accès au serveur web :</strong> 52 jours</li>
            <li><strong>Journaux système :</strong> 90 jours au maximum</li>
            <li><strong>Journaux applicatifs :</strong> 10 jours</li>
            <li><strong>Sauvegardes :</strong> 1 an</li>
            <li><strong>Versions remplacées d'un fichier Band Space :</strong> 30 jours</li>
            <li><strong>Fichiers Band Space supprimés :</strong> 30 jours, puis effacement définitif</li>
            <li><strong>Band Space supprimé :</strong> 30 jours, puis effacement définitif de l'espace et de tous ses contenus</li>
            <li><strong>Statistiques d'audience :</strong> conservées sous forme agrégée et non nominative</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            8. Vos droits
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Conformément au RGPD, vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité, ainsi que du droit de retirer votre consentement à tout moment pour les traitements qui en dépendent.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Vous pouvez supprimer votre compte directement depuis les paramètres de votre profil. Pour tout autre droit, y compris pour obtenir une copie de vos données, écrivez à
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a> : ces demandes sont traitées manuellement, aucun export automatique n'étant disponible à ce jour. Nous répondons dans un délai d'un mois.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            9. Cookies
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            MusicAll dépose uniquement les cookies nécessaires à son fonctionnement :
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>jwt_hp</strong> et <strong>jwt_s</strong> : maintien de votre session de connexion (1 heure)</li>
            <li><strong>refresh_token</strong> : renouvellement de votre session sans nouvelle saisie de mot de passe (30 jours)</li>
            <li><strong>PHPSESSID</strong> : session technique du serveur (durée de votre navigation)</li>
            <li><strong>is_dark_mode</strong> : mémorisation du thème clair ou sombre que vous avez choisi (1 an)</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Aucun cookie publicitaire, de profilage ou de suivi entre sites n'est déposé par MusicAll, et la mesure d'audience fonctionne sans cookie. Les contenus externes décrits à la section 5 peuvent en revanche déposer leurs propres cookies lorsqu'ils sont chargés.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            10. Âge minimum
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            L'inscription à MusicAll est réservée aux personnes âgées de 15 ans ou plus.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            11. Sécurité
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Les échanges avec le site sont chiffrés (HTTPS). Les mots de passe sont stockés sous forme hachée. L'accès aux contenus d'un Band Space est restreint à ses membres. La base de données fait l'objet de sauvegardes quotidiennes chiffrées, et les fichiers déposés bénéficient d'un mécanisme de versionnement.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Aucun système n'étant infaillible, nous vous recommandons d'utiliser un mot de passe unique pour votre compte MusicAll.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            12. Réclamation
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            Si vous estimez que le traitement de vos données n'est pas conforme à la réglementation, vous pouvez introduire une réclamation auprès de l'Autorité de protection des données :
          </p>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Autorité de protection des données (APD)</strong></li>
            <li>Rue de la Presse 35, 1000 Bruxelles, Belgique</li>
            <li><a href="mailto:contact@apd-gba.be" class="text-primary hover:text-primary-emphasis">contact@apd-gba.be</a></li>
            <li><a href="https://www.autoriteprotectiondonnees.be" class="text-primary hover:text-primary-emphasis" target="_blank" rel="noopener">www.autoriteprotectiondonnees.be</a></li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Si vous résidez dans un autre État membre de l'Union européenne, vous pouvez également saisir l'autorité de contrôle de votre pays de résidence.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            13. Contact
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'

useTitle('Politique de confidentialité - MusicAll')

const lastUpdate = '31 juillet 2026'
</script>
