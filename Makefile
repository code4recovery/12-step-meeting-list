PLUGIN_SLUG := 12-step-meeting-list
PLUGIN_FILE := $(PLUGIN_SLUG).php
VERSION := $(shell sed -nE "s/^define\('TSML_VERSION', '([^']+)'\).*/\1/p" $(PLUGIN_FILE))
BUILD_DIR := $(or $(BUILD_DIR),build)
ZIP_FILE := $(BUILD_DIR)/$(PLUGIN_SLUG).$(VERSION).zip

# Git ref to package. Override to cut a zip from a tag: make build REF=v3.19.17
REF := $(or $(REF),HEAD)

# Compiled output that is tracked in git; must match HEAD before a release zip.
BUILT_ASSETS := assets/build assets/css assets/js

help:  ## Print the help documentation
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# Installed only when node_modules is absent; run `make deps` to refresh it.
node_modules:
	npm ci

.PHONY: deps
deps:  ## Install/refresh JS dependencies (npm ci)
	npm ci

.PHONY: assets
assets: | node_modules  ## Compile SCSS/JS and WordPress blocks
	npm run build

# Exclusions live in .gitattributes (export-ignore); --prefix adds the
# top-level directory WordPress requires. --worktree-attributes reads
# .gitattributes from the working tree so exclusion edits apply uncommitted.
$(ZIP_FILE): assets check-assets
	@mkdir -p $(BUILD_DIR)
	rm -f $(ZIP_FILE)
	git archive --format=zip --worktree-attributes \
		--prefix=$(PLUGIN_SLUG)/ --output=$(ZIP_FILE) $(REF)
	@echo "Built $(ZIP_FILE) from $(REF)"

.PHONY: check-assets
check-assets:  ## Fail if compiled assets differ from the committed ones
ifndef ALLOW_DIRTY
	@git diff --quiet HEAD -- $(BUILT_ASSETS) || { \
		echo "ERROR: compiled assets differ from HEAD. git archive packages"; \
		echo "committed content, so these changes would NOT ship:"; \
		git status --short -- $(BUILT_ASSETS); \
		echo "Commit them, or pass ALLOW_DIRTY=1 to build anyway."; \
		exit 1; }
endif

.PHONY: build
build: $(ZIP_FILE)  ## Build the production plugin zip into build/

.PHONY: version
version:  ## Print the plugin version
	@echo $(VERSION)

.PHONY: clean
clean:  ## Remove build artifacts
	rm -rf $(BUILD_DIR)

.PHONY: distclean
distclean: clean  ## Remove build artifacts and node_modules
	rm -rf node_modules
