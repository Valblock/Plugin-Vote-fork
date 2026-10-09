# Vote [Valblock]

Fork of the official [Azuriom Vote plugin](https://github.com/Azuriom/Plugin-Vote) with **guest voting**: players vote with their username without registering on the website, and keep everything when they create their account later.

No change to the Azuriom core is required.

## How it works

1. A visitor enters a username on the vote page. If no account uses it, the username is checked with the same rules as the registration form (format, and premium account when the game requires it).
2. On the first vote, an account without email and with a random password is created for this username. Rewards are delivered exactly as for a registered player: money, commands, leaderboard, monthly goal.
3. When the player registers with the same username, on the website or in game with AzLink, the guest account is merged into the new one: votes, money, pending commands and any other data linked to the account are transferred, then the guest account is deleted.
4. Guest accounts that never received a vote or any other data are deleted after 24 hours (`vote:prune-guests`, scheduled daily).

A single connection can create at most 5 guest accounts per hour.

## Settings

*Vote > Settings > Create an account for unknown usernames* (enabled by default). The option has no effect when *Require users to be logged in to vote* is enabled.

## Installation

Replace the `plugins/vote` folder with this repository (the folder must stay named `vote`), then run the migrations from the admin panel or with `php artisan migrate`.

This fork has no market id, so Azuriom will not offer to update it. Do not install the official *Vote* plugin from the market over it: it would replace this fork.

---

## Français

Fork du plugin Vote officiel : les joueurs votent avec leur pseudo sans s'inscrire sur le site.

- Au premier vote, un compte sans email est créé pour le pseudo. Les récompenses, le classement et l'objectif mensuel fonctionnent comme pour un joueur inscrit.
- Quand le joueur s'inscrit avec le même pseudo (sur le site ou en jeu via AzLink), le compte invité est fusionné dans le nouveau compte : votes, argent et commandes en attente sont conservés.
- Les comptes invités sans aucun vote sont supprimés au bout de 24 h.
- Option : *Vote > Paramètres > Créer un compte pour les pseudos inconnus*.
- Aucune modification du cœur d'Azuriom n'est nécessaire.
