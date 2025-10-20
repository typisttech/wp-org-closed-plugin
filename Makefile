export GOFLAGS=-mod=mod

.PHONY: FORCE
FORCE:;

php-versions := 8.3 8.4
composer-versions := 2.6 2.7 2.8

define GEN_RULE
build-php-$(php)-composer-$(composer):
	docker build $(buildflags) --build-arg php=$(php) --build-arg composer=$(composer) --tag wp-org-closed-plugin:php-$(php)-composer-$(composer) .

test-php-$(php)-composer-$(composer): build-php-$(php)-composer-$(composer)
	docker run --volume $(shell pwd):/app -it --rm wp-org-closed-plugin:php-$(php)-composer-$(composer)
endef

combos :=
$(foreach php,$(php-versions), \
	$(foreach composer,$(composer-versions), \
		$(eval $(GEN_RULE)) \
		$(eval combos += php-$(php)-composer-$(composer)) \
	) \
)

test: $(foreach c,$(combos), test-$(c))

clean:
	@IMAGE_IDS="$(shell docker images -q wp-org-closed-plugin)"; \
	if test -z "$${IMAGE_IDS}"; then \
		echo "Skip: No wp-org-closed-plugin images found."; \
	else \
		docker rmi $${IMAGE_IDS}; \
	fi

build-latest: build-php-8-composer-latest
build-php-8-composer-latest:
	docker build $(buildflags) --build-arg php=8 --build-arg composer=latest --tag wp-org-closed-plugin:php-8-composer-latest .

test-latest: test-php-8-composer-latest
test-php-8-composer-latest: build-php-8-composer-latest
	docker run --volume $(shell pwd):/app -it --rm wp-org-closed-plugin:php-8-composer-latest

test-local:
	go test -v ./...
