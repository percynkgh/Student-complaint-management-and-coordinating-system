# TODO - Upvote priority not updating after thresholds

- [x] Patch `Student/upvote_complaint.php` to update `complaints.priority` + `affected_count` after upvotes cross thresholds:
  - upvotes > 20 => urgent
  - upvotes > 10 => high
  - else keep existing priority (no change)
- [x] Patch `Student/view_complaint.php` POST upvote handler to apply the same priority/affected_count update (for consistency)
- [x] Quick sanity-check for SQL columns existence: `priority`, `affected_count`, `upvote_count`
- [x] Run a PHP syntax check (lint) on modified files
