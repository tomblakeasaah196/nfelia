-- Content-policy reports (docs/ops/SECURITY.md §6). Browsers post what the
-- Content-Security-Policy would have blocked to /api/csp, and api/ajax/csp.php
-- records each report as an error of kind "csp", grouped by directive and
-- foreign host. The kind column is an ENUM, so it has to know the word first;
-- without this the insert fails quietly and nothing shows in Ops → Errors.
ALTER TABLE `error_groups`
    MODIFY `kind` ENUM('php','js','csp','slow','log','job') NOT NULL;
