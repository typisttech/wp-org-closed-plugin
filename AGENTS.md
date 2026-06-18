## Commands

```bash
# Run all tests, PHP & integration
mise run test

# Run PHP tests
mise run test:php

# Integration tests use the testscript framework; scripts live in `testdata/script/*.txtar`
mise run test:e2e

# Regenerate testscript golden files when output intentionally changed
mise run gen

# Run linters
mise run lint

# Format & fix linting issues
mise run lint:fix
```
