COMPOSE := docker compose

.DEFAULT_GOAL := help
.PHONY: help up down restart rebuild build migrate seed fresh logs shell tinker test key bot-keyword bot-ai

help: ## Liste les commandes disponibles
	@grep -E '^[a-z-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Démarre la pile complète
	$(COMPOSE) up -d --build

down: ## Arrête la pile
	$(COMPOSE) down

restart: ## Redémarre les services applicatifs
	$(COMPOSE) restart app queue reverb

rebuild: ## Reconstruit l'image et rafraîchit vendor/ et public/build
	$(COMPOSE) up -d --build --renew-anon-volumes

key: ## Génère APP_KEY dans .env
	$(COMPOSE) run --rm app php artisan key:generate

migrate: ## Applique les migrations
	$(COMPOSE) exec app php artisan migrate --force

seed: ## Charge les données de démonstration
	$(COMPOSE) exec app php artisan db:seed --force

fresh: ## Remet la base à zéro et recharge les données de démonstration
	$(COMPOSE) exec app php artisan migrate:fresh --seed --force

logs: ## Suit les logs des trois services applicatifs
	$(COMPOSE) logs -f app queue reverb

shell: ## Ouvre un shell dans le conteneur applicatif
	$(COMPOSE) exec app sh

tinker: ## Ouvre une console Tinker
	$(COMPOSE) exec app php artisan tinker

test: ## Lance la suite de tests
	$(COMPOSE) exec app php artisan test

bot-keyword: ## Bascule le bot en mode mots-clés
	$(COMPOSE) exec app php artisan bot:mode keyword

bot-ai: ## Bascule le bot en mode IA
	$(COMPOSE) exec app php artisan bot:mode ai
