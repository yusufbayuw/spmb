---
paths:
  - app/Models/Registration.php
---

# Models

## Do not infer workflow conflicts from affected row counts
A repeated document_verification transition may change no values, so MySQL/MariaDB can return zero affected rows even when the registration matches. Validate the expected source stage and active lifecycle on a locked row within a transaction; keep stale-stage and cancelled-lifecycle requests rejected. The document upload regression test emulates zero-row no-op updates on SQLite.
