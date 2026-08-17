# Independent final audit — corrective pass after developer final_5

Source developer archive SHA-256:

`e07b2ddcbd5f79a98a330b4108fecab05fa3e91058fd3661642ac5ae894788ee`

Two residual issues were reproduced and corrected before this final candidate was rebuilt:

1. Lost-artifacts recovery was marker-scoped only at file level. With two supported mechanisms in the same `config.php`, an array marker could cause an unowned/manual `$redirect_enabled=0` to be changed to `1`. Recovery is now mechanism-scoped: each rule must prove its own marker evidence.
2. Standalone rollback could print `ROLLBACK_OK` when install metadata required restoring a pre-existing repair CLI but the backed-up CLI file was missing. CLI state is now inside the rollback transaction; this case aborts safely with exit 99 and restores the pre-rollback candidate state.

Independent checks after these corrections:

- package PHP lint: PASS;
- shell syntax: PASS;
- all 16 non-DB test suites: PASS;
- same-file mixed config mechanisms regression: PASS;
- normal installer run on REV3.1 hash baseline: PASS;
- commit-phase installer failure after service copies: byte-exact BASE rollback PASS;
- installer adversarial matrix: 33 PASS / 0 FAIL;
- rollback adversarial matrix: 11 PASS / 0 FAIL;
- standalone rollback normal: exact REV3.1 SHA PASS;
- pre-existing repair CLI normal rollback: byte-identical restore PASS;
- missing backed-up pre-existing CLI: safe abort exit 99 + pre-rollback services/CLI restored PASS;
- rollback payload files match `BASE_SHA256.txt`: PASS;
- manifest verified after final file changes: PASS.

The supplied developer DB evidence remains `17 PASS / 0 FAIL` on a disposable MariaDB database. The independent review environment has no PDO-MySQL driver, therefore this DB suite was not re-executed independently after the two corrections. Those corrections do not touch reservation/state-machine SQL; they are covered by the non-DB recovery and rollback tests above.

Production still requires the normal canary: backup production, install only on the expected REV3.1 hashes, observe existing job #227 through `update_classes_call`, and verify that it leaves the stage without a duplicate mutation and later restores the redirect.
