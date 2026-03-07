# Guide de publication — Lescopr PHP SDK

Ce guide couvre tout le cycle de publication : Packagist, Laravel News, Symfony Flex,
GitHub Releases et le versioning sémantique.

---

## Stores / registres à publier

Un seul package PHP (`lescopr/lescopr-php` sur **Packagist**) couvre **tous** les
frameworks. Il n'y a **pas** de package séparé par framework — Composer est
l'unique gestionnaire de dépendances PHP et Packagist est son seul store officiel.

| Store / registre | Obligatoire | Ce qu'il apporte |
|---|---|---|
| **[Packagist](https://packagist.org)** | ✅ Oui | `composer require lescopr/lescopr-php` — disponible pour tous les projets PHP |
| **[GitHub Releases](https://github.com/Lescopr/lescopr-php/releases)** | ✅ Oui | Source des tags que Packagist indexe, notes de release, changelog public |
| **[Symfony Flex recipes](https://github.com/symfony/recipes-contrib)** | ⭐ Recommandé | Auto-configure le bundle et `services.yaml` à l'install dans Symfony |
| **[Laravel News Packages](https://laravel-news.com/packages)** | ⭐ Recommandé | Visibilité dans la communauté Laravel, trafic organique |
| **[Awesome Laravel](https://github.com/chiraggude/awesome-laravel)** | Optionnel | Liste communautaire |

> **Résumé** : publiez sur Packagist + créez les GitHub Releases.
> Le reste (Symfony Flex, Laravel News) est du **marketing**, pas une obligation technique.

---

## Étape 1 — Publication initiale sur Packagist

### 1.1 Créer un compte Packagist

1. Allez sur <https://packagist.org/register>
2. Connectez votre compte GitHub (recommandé pour l'auto-sync)

### 1.2 Soumettre le package

1. Allez sur <https://packagist.org/packages/submit>
2. Entrez l'URL du dépôt : `https://github.com/Lescopr/lescopr-php`
3. Cliquez **Check** puis **Submit**

Packagist va lire votre `composer.json` et indexer le package.

### 1.3 Configurer le webhook GitHub (auto-update)

Dans les **Settings GitHub du repo** → **Webhooks** → **Add webhook** :

```
Payload URL:  https://packagist.org/api/github?username=VOTRE_USERNAME_PACKAGIST
Content type: application/json
Secret:       (laisser vide ou utiliser le token Packagist)
Events:       ✅ Just the push event
```

**OU** utilisez l'intégration officielle GitHub :
Settings → **Integrations** → **Packagist** → Enter username + token.

### 1.4 Configurer les secrets GitHub Actions

Dans votre repo GitHub → **Settings** → **Secrets and variables** → **Actions** :

| Secret | Valeur |
|---|---|
| `PACKAGIST_USERNAME` | Votre username Packagist (ex: `sonnalabs`) |
| `PACKAGIST_TOKEN` | Token API Packagist (généré sur <https://packagist.org/profile/>) |

---

## Étape 2 — Versioning sémantique

Le projet suit **[SemVer](https://semver.org)** strict :

```
MAJOR.MINOR.PATCH[-pre-release]

0.1.0        → première release stable
0.1.1        → bugfix
0.2.0        → nouvelle feature, rétro-compatible
1.0.0        → API stable et finalisée
1.0.0-beta.1 → pre-release (marquée automatiquement sur GitHub)
```

### Règle pratique

| Type de changement | Incrément |
|---|---|
| Bug fix, correction doc | `PATCH` (0.1.x) |
| Nouvelle feature, nouvelle intégration | `MINOR` (0.x.0) |
| Breaking change (API incompatible) | `MAJOR` (x.0.0) |

---

## Étape 3 — Workflow de release

### Release standard (script automatisé)

```bash
# Depuis la racine du SDK PHP
./scripts/release.sh 0.2.0
```

Ce script :
1. Vérifie que les tests passent (`composer test:ci`)
2. Met à jour `CHANGELOG.md` ([Unreleased] → [0.2.0])
3. Crée un commit `chore: release v0.2.0`
4. Crée le tag annoté `v0.2.0`
5. Push sur `main` + push le tag

**GitHub Actions prend le relai** (`.github/workflows/release.yml`) :
- Rerun les tests
- Crée la GitHub Release avec les notes du CHANGELOG
- Notifie Packagist via API → mise à jour immédiate

### Release manuelle (si le script ne peut pas être utilisé)

```bash
# 1. Mettre à jour CHANGELOG.md manuellement
# 2. Commit
git add CHANGELOG.md
git commit -m "chore: release v0.2.0"

# 3. Tag annoté (obligatoire pour Packagist)
git tag -a v0.2.0 -m "Release v0.2.0"

# 4. Push
git push origin main
git push origin v0.2.0
```

---

## Étape 4 — CHANGELOG.md : convention

Toujours maintenir `## [Unreleased]` en haut du CHANGELOG pour accumuler les
changements en cours. Lors d'une release, le script `release.sh` déplace
automatiquement `[Unreleased]` vers la version taguée.

```markdown
## [Unreleased]            ← vos prochains changements ici

---

## [0.2.0] — 2026-04-15

### Added
- Nouvelle feature X

### Fixed
- Bug Y

---

## [0.1.0] — 2026-03-07
...
```

---

## Étape 5 — Symfony Flex Recipe (optionnel mais recommandé)

Une Flex recipe permet l'installation **zero-config** dans Symfony :
`composer require lescopr/lescopr-php` configure automatiquement le bundle,
le monolog handler et les services.

### Soumettre une recipe

1. Forkez <https://github.com/symfony/recipes-contrib>
2. Créez le dossier `lescopr/lescopr-php/0.1/`
3. Ajoutez les fichiers de configuration :

**`manifest.json`** :
```json
{
    "bundles": {
        "Lescopr\\Integrations\\Symfony\\LescoprBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    }
}
```

**`config/packages/lescopr.yaml`** :
```yaml
# config/packages/lescopr.yaml
monolog:
    handlers:
        lescopr:
            type: service
            id: Lescopr\Integrations\Symfony\LescoprMonologHandler
            channels: ['!event', '!doctrine']
```

4. Ouvrez une Pull Request sur `symfony/recipes-contrib`

---

## Étape 6 — Laravel News (optionnel)

Soumettez votre package sur <https://laravel-news.com/submit-a-package> :
- Package name : `lescopr/lescopr-php`
- GitHub URL : `https://github.com/Lescopr/lescopr-php`
- Description courte (max 160 car.)

---

## Récapitulatif des commandes

```bash
# 1. Première publication (une seule fois)
#    → Aller sur https://packagist.org/packages/submit

# 2. Chaque nouvelle release
./scripts/release.sh 0.2.0

# 3. Vérifier l'état sur Packagist
open https://packagist.org/packages/lescopr/lescopr-php

# 4. Vérifier le workflow GitHub Actions
open https://github.com/Lescopr/lescopr-php/actions
```

