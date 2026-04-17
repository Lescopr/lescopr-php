# =============================================================================
# Makefile — Lescopr PHP SDK
#
# Targets:
#   make version        → affiche la version actuelle (SDK_VERSION constant)
#   make bump-patch     → incrémente PATCH (0.1.0 → 0.1.1) et publie
#   make bump-minor     → incrémente MINOR (0.1.0 → 0.2.0) et publie
#   make bump-major     → incrémente MAJOR (0.1.0 → 1.0.0) et publie
#   make release V=x.y.z→ publie une version précise
#   make test           → lance les tests PHPUnit
#   make push           → git push origin main
#   make tag            → crée et pousse le tag git de la version courante
# =============================================================================

.DEFAULT_GOAL := help

VERSION_FILE  := src/Core/Lescopr.php

# ── Lire la version depuis la constante SDK_VERSION ──────────────────────────
CURRENT_VERSION := $(shell grep "SDK_VERSION" $(VERSION_FILE) | sed "s/.*'\(.*\)'.*/\1/")

# ── Décomposer en MAJOR.MINOR.PATCH ──────────────────────────────────────────
VERSION_MAJOR := $(word 1,$(subst ., ,$(CURRENT_VERSION)))
VERSION_MINOR := $(word 2,$(subst ., ,$(CURRENT_VERSION)))
VERSION_PATCH := $(word 3,$(subst ., ,$(CURRENT_VERSION)))

NEXT_PATCH    := $(shell echo $$(($(VERSION_PATCH) + 1)))
NEXT_MINOR    := $(shell echo $$(($(VERSION_MINOR) + 1)))
NEXT_MAJOR    := $(shell echo $$(($(VERSION_MAJOR) + 1)))

.PHONY: help version bump-patch bump-minor bump-major release test push tag _bump

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

version: ## Affiche la version courante
	@echo "Version courante : $(CURRENT_VERSION)"

# ── Cible interne : met à jour SDK_VERSION avant d'appeler release.sh ────────
_bump:
	@echo "📝  Mise à jour de SDK_VERSION dans $(VERSION_FILE) → $(NEW_V)"
	@sed -i '' "s/SDK_VERSION = '[^']*'/SDK_VERSION = '$(NEW_V)'/" $(VERSION_FILE)
	@git add $(VERSION_FILE)

bump-patch: ## Incrémente PATCH et publie (0.1.0 → 0.1.1)
	@echo "🔼  Bump PATCH : $(CURRENT_VERSION) → $(VERSION_MAJOR).$(VERSION_MINOR).$(NEXT_PATCH)"
	@$(MAKE) _bump NEW_V="$(VERSION_MAJOR).$(VERSION_MINOR).$(NEXT_PATCH)"
	@bash scripts/release.sh "$(VERSION_MAJOR).$(VERSION_MINOR).$(NEXT_PATCH)"

bump-minor: ## Incrémente MINOR et publie (0.1.x → 0.2.0)
	@echo "🔼  Bump MINOR : $(CURRENT_VERSION) → $(VERSION_MAJOR).$(NEXT_MINOR).0"
	@$(MAKE) _bump NEW_V="$(VERSION_MAJOR).$(NEXT_MINOR).0"
	@bash scripts/release.sh "$(VERSION_MAJOR).$(NEXT_MINOR).0"

bump-major: ## Incrémente MAJOR et publie (0.x.y → 1.0.0)
	@echo "🔼  Bump MAJOR : $(CURRENT_VERSION) → $(NEXT_MAJOR).0.0"
	@$(MAKE) _bump NEW_V="$(NEXT_MAJOR).0.0"
	@bash scripts/release.sh "$(NEXT_MAJOR).0.0"

release: ## Publie une version précise  (usage : make release V=0.2.0)
ifndef V
	$(error ❌  Spécifie la version : make release V=0.2.0)
endif
	@$(MAKE) _bump NEW_V="$(V)"
	@bash scripts/release.sh "$(V)"

test: ## Lance les tests PHPUnit
	LESCOPR_DAEMON_MODE=true composer test:ci

push: ## Pousse la branche main sur origin
	git push origin main

tag: ## Crée et pousse le tag git de la version courante
	@echo "🔖  Création du tag v$(CURRENT_VERSION)..."
	git tag -a "v$(CURRENT_VERSION)" -m "Release v$(CURRENT_VERSION)"
	git push origin "v$(CURRENT_VERSION)"
	@echo "✅  Tag v$(CURRENT_VERSION) poussé."
