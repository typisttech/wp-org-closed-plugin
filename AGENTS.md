## Commands

```bash
# Run PHP tests
composer test

# Integration tests use the testscript framework; scripts live in `testdata/script/*.txtar`
GOFLAGS=-mod=mod go test -count=1 -shuffle=on ./...

# Run linters
composer lint
```
