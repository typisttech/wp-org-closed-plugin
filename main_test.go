package main_test

import (
	"os"
	"testing"

	"github.com/rogpeppe/go-internal/testscript"
)

func Test(t *testing.T) {
	testscript.Run(t, testscript.Params{
		Dir: "testdata/script",
		Setup: func(env *testscript.Env) error {
			wd, err := os.Getwd()
			if err != nil {
				return err
			}
			env.Setenv("PWD", wd)

			env.Setenv("COLUMNS", "120")
			return nil
		},
		UpdateScripts: os.Getenv("UPDATE_SCRIPTS") == "1",
	})
}
