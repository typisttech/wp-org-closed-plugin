export GOFLAGS=-mod=mod

combos := php-8-composer-latest \
	php-8.5-composer-2.9 \
	php-8.5-composer-2.8 \
	php-8.4-composer-2.9 \
	php-8.4-composer-2.8 \
	php-8.3-composer-2.9 \
	php-8.3-composer-2.8

buildflags ?= --quiet

define GEN_RULE
build-php-$(1)-composer-$(2):
	docker build $(buildflags) --build-arg php=$(1) --build-arg composer=$(2) --tag wp-org-closed-plugin:php-$(1)-composer-$(2) .

test-php-$(1)-composer-$(2): build-php-$(1)-composer-$(2)
	docker run --volume $(shell pwd):/app --rm wp-org-closed-plugin:php-$(1)-composer-$(2) $(testcmd)
endef

$(foreach combo,$(combos), $(eval $(call GEN_RULE,$(word 2,$(subst -, ,$(combo))),$(word 4,$(subst -, ,$(combo))))))

test: $(foreach c,$(combos), test-$(c))

build-latest: build-php-8-composer-latest
test-latest: test-php-8-composer-latest

test-local:
	go test -count=1 -shuffle=on ./...

update-scripts:
	UPDATE_SCRIPTS=1 $(MAKE) test-local

clean:
	@IMAGE_IDS="$(shell docker images -q wp-org-closed-plugin)"; \
	if test -z "$${IMAGE_IDS}"; then \
		echo "Skip: No wp-org-closed-plugin images found."; \
	else \
		docker rmi --force $${IMAGE_IDS}; \
	fi
