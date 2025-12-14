.PHONY: help install test lint analyze check build deploy clean ci hooks release watch-dev

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies
	composer install

install-dev: ## Install dependencies including dev tools
	composer install --dev

test: ## Run all tests
	composer test

test-unit: ## Run unit tests only
	composer test:unit

test-integration: ## Run integration tests only
	composer test:integration

test-e2e: ## Run E2E tests only
	composer test:e2e

coverage: ## Generate test coverage report
	composer test:coverage
	@echo "Coverage report: coverage/index.html"
	@open coverage/index.html 2>/dev/null || true

lint: ## Check code style (PSR-12)
	composer lint

lint-fix: ## Fix code style issues automatically
	composer lint:fix

analyze: ## Run static analysis (PHPStan level 8)
	composer analyze

check-js: ## Validate JavaScript syntax
	composer check:js

check: ## Run all quality checks (lint + analyze + test)
	composer check

ci: ## Run full CI pipeline locally
	./scripts/ci-local.sh

hooks: ## Install Git pre-commit hooks
	./scripts/install-hooks.sh

build: ## Build plugin package
	./build.sh

deploy: ## Deploy to test server
	./deploy.sh

release: ## Create tagged release (usage: make release VERSION=2025.01.19)
	@if [ -z "$(VERSION)" ]; then \
		echo "Error: VERSION required. Usage: make release VERSION=2025.01.19"; \
		exit 1; \
	fi
	./scripts/release.sh $(VERSION) "$(NOTES)"

watch-dev: ## Watch for changes and auto-deploy (development mode)
	./scripts/dev-watch.sh

clean: ## Clean build artifacts
	rm -rf build/ archive/ coverage/ vendor/
	find . -name '.phpunit.result.cache' -delete

clean-all: clean ## Clean everything including node_modules
	rm -rf node_modules/

verify: ## Verify package integrity
	./verify-package.sh

status: ## Show project status
	@echo "╔════════════════════════════════════════════════════════════╗"
	@echo "║  Project Status                                            ║"
	@echo "╚════════════════════════════════════════════════════════════╝"
	@echo ""
	@echo "Git branch:    $$(git rev-parse --abbrev-ref HEAD)"
	@echo "Last commit:   $$(git log -1 --pretty=format:'%h - %s (%cr)')"
	@echo "Modified files: $$(git status --porcelain | wc -l | tr -d ' ')"
	@echo ""
	@echo "Dependencies:"
	@echo "  Composer:    $$([ -d vendor ] && echo '✓ Installed' || echo '✗ Not installed')"
	@echo "  Node:        $$([ -d node_modules ] && echo '✓ Installed' || echo '✗ Not installed')"
	@echo ""
	@echo "Tests:"
	@composer test 2>&1 | tail -1 || echo "  Run 'make test' to check"
	@echo ""
	@echo "Build artifacts:"
	@echo "  Package:     $$([ -d archive ] && ls -1 archive/*.txz 2>/dev/null | wc -l | tr -d ' ' || echo '0') file(s)"
	@echo "  Coverage:    $$([ -d coverage ] && echo '✓ Generated' || echo '✗ Not generated')"
