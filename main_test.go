package main_test

import (
	"fmt"
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/rogpeppe/go-internal/testscript"
	"golang.org/x/mod/semver"
)

func Test(t *testing.T) {
	us := slices.Contains([]string{"1", "true"}, os.Getenv("UPDATE_SCRIPTS"))
	if us {
		t.Log("Updating test scripts")
	}

	testscript.Run(t, testscript.Params{
		Dir:           "testdata/script",
		UpdateScripts: us,
		Setup: func(env *testscript.Env) error {
			wd, err := os.Getwd()
			if err != nil {
				return err
			}
			env.Setenv("PWD", wd)
			env.Setenv("COMPOSER_CACHE_DIR", "/dev/null")
			return nil
		},
		Condition: func(cond string) (bool, error) {
			targ, ok := strings.CutPrefix(cond, "composer:")
			if !ok {
				return false, fmt.Errorf("unknown condition: %s", cond)
			}

			// This is set by GitHub Actions.
			cur := os.Getenv("COMPOSER_VERSION")
			cur = "v" + cur
			if cur == "" || !semver.IsValid(cur) {
				// Assume latest composer version when running outside containers.
				return true, nil
			}

			targ = "v" + targ + ".0"

			if !semver.IsValid(targ) {
				return false, fmt.Errorf("invalid composer version: %q", targ)
			}

			return semver.Compare(cur, targ) >= 0, nil
		},
	})
}
