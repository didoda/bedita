# The following env variables need to be set:
# - VERSION
# - GITHUB_USER
# - GITHUB_TOKEN (optional if you have two factor authentication in github)

# Use the version number to figure out if the release
# is a pre-release
PRERELEASE=$(shell echo $(VERSION) | grep -E 'dev|rc|alpha|beta' --quiet && echo 'true' || echo 'false')
CURRENT_BRANCH=$(shell git branch | grep '*' | tr -d '* ')

# composer version to use. Leave empty to use last version.
COMPOSER_VERSION=

# Github settings
UPLOAD_HOST=https://uploads.github.com
API_HOST=https://api.github.com
OWNER=bedita
REMOTE=origin

ifdef GITHUB_TOKEN
	AUTH=-H 'Authorization: token $(GITHUB_TOKEN)'
else
	AUTH=-u $(GITHUB_USER) -p$(GITHUB_PASS)
endif

DASH_VERSION=$(shell echo $(VERSION) | sed -e s/\\./-/g)

# Version related vars
VERSION_CONTENT="[BEdita] \nversion = "
VERSION_FILE="plugins/BEdita/Core/config/bedita.ini"
VERSION_TAG=v$(VERSION)

# Main branch for BEdita 4
MAIN_BRANCH:=4-cactus


ALL: help
.PHONY: help install test need-version bump-version tag-version

help:
	@echo "BEdita Makefile"
	@echo "================"
	@echo ""
	@echo "release VERSION=x.y.z"
	@echo "  Create a new release of BEDITA. Requires the VERSION and GITHUB_USER, or GITHUB_TOKEN parameter."
	@echo "  Packages up a new app skeleton tarball and uploads it to github."
	@echo ""
	@echo "package"
	@echo "  Build the main package with all its dependencies."
	@echo ""
	@echo "publish"
	@echo "  Publish the dist/bedita-VERSION.zip to github."
	@echo ""
	@echo "All other tasks are not intended to be run directly."


# Utility target for checking required parameters
guard-%:
	@if [ "$($*)" = '' ]; then \
		echo "Missing required $* variable."; \
		exit 1; \
	fi;


# Download composer
composer.phar:
	curl -sS https://getcomposer.org/installer | php;
	@if [ ! -z "$COMPOSER_VERSION" ]; then \
		php composer.phar self-update ${COMPOSER_VERSION}; \
	fi;

# Install dependencies
install: composer.phar
	php composer.phar install


# Version bumping & tagging for BEdita itself
# Update $(VERSION_FILE) to new version.
bump-version: guard-VERSION
	@echo "Update version to $(VERSION) in $(VERSION_FILE)"
	echo $(VERSION_CONTENT)$(VERSION) > $(VERSION_FILE)
	git add $(VERSION_FILE)
	git commit -m "chore: update version number to $(VERSION) [ci skip]"

# Tag a release
tag-release: guard-VERSION bump-version
	@echo "Tagging release $(VERSION)"
	git tag -a $(VERSION_TAG) -m "BEdita $(VERSION)"
	git push $(REMOTE)
	git push $(REMOTE) --tags


# Tasks for tagging the app skeleton and
# creating a zipball of a fully built app skeleton.
.PHONY: clean package

clean:
	rm -rf build
	rm -rf dist

build:
	mkdir -p build

build/bedita: build
	git clone git@github.com:$(OWNER)/bedita.git build/bedita/
	cd build/bedita && git checkout $(VERSION_TAG)

dist/bedita-$(DASH_VERSION).zip: build/bedita composer.phar
	mkdir -p dist
	@echo "Installing app dependencies with composer"
	# Install deps with composer
	cd build/bedita && php ../../composer.phar install
	# Make a zipball of all the files that are not in .git dirs
	# Including .git will make zip balls huge, and the zipball is
	# intended for quick start non-git, non-cli users
	@echo "Building zipball for $(VERSION)"
	cd build/bedita && find . -not -path '*.git*' | zip ../../dist/bedita-$(DASH_VERSION).zip -@

# Easier to type alias for zip balls
package: clean dist/bedita-$(DASH_VERSION).zip


# Tasks to publish zipballs to Github.
.PHONY: publish release

publish: guard-VERSION guard-GITHUB_USER dist/bedita-$(DASH_VERSION).zip
	@echo "Creating draft release for $(VERSION). prerelease=$(PRERELEASE)"
	curl $(AUTH) -XPOST $(API_HOST)/repos/$(OWNER)/bedita/releases -d '{"tag_name": "$(VERSION)", "name": "BEdita $(VERSION) released", "draft": true, "prerelease": $(PRERELEASE)}' > release.json
	# Extract id out of response json.
	php -r '$$f = file_get_contents("./release.json"); $$d = json_decode($$f, true); file_put_contents("./id.txt", $$d["id"]);'
	@echo "Uploading zip file to github."
	curl $(AUTH) -XPOST \
		$(UPLOAD_HOST)/repos/$(OWNER)/bedita/releases/`cat ./id.txt`/assets?name=bedita-$(DASH_VERSION).zip \
		-H "Accept: application/vnd.github.manifold-preview" \
		-H 'Content-Type: application/zip' \
		--data-binary '@dist/bedita-$(DASH_VERSION).zip'
	# Cleanup files.
	rm release.json
	rm id.txt

# Top level alias for doing a release.
release: guard-VERSION guard-GITHUB_USER tag-release package publish
