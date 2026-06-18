# Récap mensuel par mail

Doc d'orientation pour LLM. Lis le **Pourquoi (besoin métier)** avant de toucher au code : la plupart
des choix techniques ci-dessous ne se comprennent qu'au regard de l'objectif produit.

## Pourquoi — besoin métier

Pixia s'acquiert via les réseaux sociaux et vit sur le **rappel quotidien**. L'email mensuel est un
levier de **rétention / réengagement** : un seul point de contact par mois pour **ramener le joueur**,
sans le spammer. Il doit :

- **Rappeler l'existence de Pixia** et donner envie de rejouer (CTA « image du jour »).
- **Flatter / motiver** par la performance du mois passé : classement (rang + écart au champion),
  parties gagnées, points, **badges** débloqués → entretient la compétition et le sentiment de progression.
- **Valoriser les créateurs** : combien de joueurs ont deviné leurs Pixias, en combien de tentatives.
- **Pousser à créer** : solde de tokens pixia + ce qu'il manque pour générer le prochain Pixia.
- **Respecter le joueur** : consentement explicite, désinscription en 1 clic, et **jamais deux fois le
  même mail** (confiance + délivrabilité de l'expéditeur).

Le **ton** (punchline selon le rang, citation de film par mois) sert la **DA / l'identité de marque**
(rétro, ciné, taquin) — pas du remplissage.

## Flux

1. **Cron le 1er du mois** → `app:mail:monthly-recap` (`SendMonthlyRecapCommand`).
2. Mois récapé = mois **précédent** (`clock->now()->modify('first day of last month')`).
3. `UserRepository::findEmailNotificationRecipients($sentAfter)` → destinataires.
4. `MonthlyRecapService::getRecapFor()` → DTO `MonthlyRecap` (typé).
5. `MailService::sendMonthlyRecapMail()` → template `templates/mail/monthly_recap.html.twig`.
6. Après chaque envoi réussi : `user.monthlyRecapSentAt = now` + `flush`.
7. **Désinscription** : lien signé → `UnsubscribeController` (`GET /unsubscribe`).

## Règles métier (et leur raison)

| Règle | Raison métier |
|-------|---------------|
| Destinataires = `emailConsent = true`, ni `ROLE_BOT` ni `ROLE_BANNED`, pas déjà servi ce mois | RGPD + on n'écrit qu'aux vrais joueurs actifs |
| `monthlyRecapSentAt` posé après envoi ; requête exclut les déjà-servis | **Idempotent + resumable** : un re-run (cron rejoué, échec partiel) ne renvoie qu'aux restants → **aucun doublon** |
| Punchline = `RecapTier` selon le rang : CHAMPION(1), PODIUM(2-3), TOP_10(4-10), TOP_50(11-50), TOP_100(51-100), RANKED(101+), INACTIVE(0 pt) | Personnaliser le ton selon la perf → motivation / réengagement |
| Citation ciné = 1 par mois calendaire (`MonthlyRecapService::movieQuote`) | Identité de marque, clin d'œil ludique |
| Champion + écart calculés sur le score `MonthlyLeaderboard` **du mois** | Compétition mensuelle, pas le score global figé |
| Si le destinataire **est** le champion : on affiche quand même son nom (« 0 pts devant toi ») | Choix assumé « affichage identique pour tous » (simplicité) |
| Pixias créés = games de l'auteur **APPROVED et datés dans le mois** ; `winners`/`attempts` depuis `Game` | Seuls les Pixias publiés ont des devineurs ; valorise le créateur |
| Seuil création = `TokenCost::CREATION_MINIMUM` (1000) → `tokensMissing`/`tokensPercent` | Inciter à accumuler/dépenser des tokens pixia |
| **Bots inclus** dans champion / rang / compteurs de devineurs | Les bots animent les leaderboards in-app ; le mail reste **cohérent** avec ce que voit le joueur (voulu) |

## Fichiers

| Rôle | Fichier |
|------|---------|
| Commande cron (`--dry-run`) | `src/Command/SendMonthlyRecapCommand.php` |
| Construction des données | `src/Service/MonthlyRecapService.php` |
| DTO de vue (typé) | `src/DTO/MonthlyRecap.php` |
| Punchline selon le rang | `src/Enum/RecapTier.php` |
| Libellés/icônes badges | `src/Enum/BadgeName.php` |
| Envoi + lien signé | `src/Service/MailService.php` |
| Désinscription | `src/Controller/UnsubscribeController.php` |
| Template | `templates/mail/monthly_recap.html.twig` |
| Champs entité | `User::$emailConsent`, `User::$monthlyRecapSentAt` |
| Requêtes | `UserRepository::findEmailNotificationRecipients`, `AchievementRepository::countByPlayerForMonth`, `BadgeRepository::findByPlayerForMonth`, `GameRepository::findAuthoredInMonthWithWinners`, `MonthlyLeaderboardRepository::findChampionForMonth`/`getMonthlyRank` |

## Config / env

- `DEFAULT_URI` — hôte pour les URL absolues en CLI (lien de désinscription). Doit valoir **exactement**
  l'hôte cliqué (la signature `UriSigner` est liée au scheme+host). En prod : URL publique réelle de l'API
  **+ trusted proxies Railway**, sinon `checkRequest` échoue → 403 sur tous les liens.
- `FRONTEND_URL` — base vers laquelle l'unsubscribe redirige (`/?unsubscribed`).
- **Verrou dev/test** (`config/packages/mailer.yaml`) : `envelope.recipients` force tout vers
  `mirage.pixia@gmail.com` en `when@dev`/`when@test` → **impossible d'atteindre un vrai joueur hors prod**.
- `ext-intl` requis (`IntlDateFormatter`, nom du mois FR), déclaré dans `composer.json`.

## Pièges connus

- **Précédence `OR` Doctrine** — dans `findEmailNotificationRecipients`, le `OR` est **parenthésé** :
  `'(u.monthlyRecapSentAt IS NULL OR u.monthlyRecapSentAt < :sentAfter)'`. Sans parenthèses, `AND` prime
  et la branche `OR` court-circuite les filtres consentement/bot/banni → **un désinscrit/banni reçoit le
  mail**. Ne jamais retirer les parenthèses. Non-régression : `MonthlyRecapRecipientsTest`.
- **Mail synchrone** — envoi en boucle (Brevo). OK pour quelques centaines/mois ; passer à Symfony
  Messenger (async) au-delà, sinon risque de timeout / rate-limit.

## Tests

| Fichier | Couvre |
|---------|--------|
| `tests/Unit/Enum/RecapTierTest.php` | Bornes de `fromRank` + punchlines non vides |
| `tests/Unit/Service/MonthlyRecapServiceTest.php` | Construction du DTO (mocks) + mapping **mois → citation** |
| `tests/Functional/MonthlyRecapRecipientsTest.php` | Filtre destinataires : consentement, bot, banni, **bug `OR`**, idempotence |
| `tests/Functional/UnsubscribeEndpointTest.php` | Lien signé → 302 + `emailConsent=false` ; signature invalide → 400 |
| `tests/Functional/SendMonthlyRecapCommandTest.php` | Envoi + marquage `monthlyRecapSentAt` + **idempotence au re-run** + dry-run inoffensif |
| `tests/Unit/Serialization/UserGroupSerializationTest.php` | `emailConsent` exposé dans `user:me` |

## TODO

- Envoi async (Symfony Messenger) quand le volume grandit.
- Filtre optionnel des inactifs chroniques / nouveaux inscrits (jugé non prioritaire : un mail = un rappel).
