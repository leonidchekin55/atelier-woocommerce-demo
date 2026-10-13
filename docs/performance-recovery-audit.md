# Performance and recovery audit — 2026-10-13

Scope: free Atelier WordPress/WooCommerce demo, starting from `826ef71` on a
clean checkout. Changes prepared in `fix/performance-recovery-audit`.

## Measured performance

Home photos now use responsive HTML images. The hero is eager/high priority;
maker/category photos use native lazy loading. Photo crops, gradients and
layout are preserved. No third-party optimization service or paid plugin is
required. Only theme static assets receive one-week public caching; cart
responses retain `no-cache, must-revalidate, max-age=0, private`.

Local Chromium, 390x844 px, DPR 1, browser cache disabled, 150 ms latency,
200 kB/s download, 4x CPU throttling, three runs:

| Measurement | Before | After |
| --- | ---: | ---: |
| Encoded resources on initial viewport | 1,412,474 bytes | approximately 406,554 bytes |
| Median LCP | 6.40 s | 4.13 s |
| Median LCP minus TTFB | 6.10 s | 2.39 s |

Initial transfer dropped about 71%. Server load differed during concurrent
Docker work, so these are local laboratory measurements, not a promised
production Core Web Vitals score. The existing Render site's first uncached
measurement had a 23.9 s LCP, while a warm reload had 1.50 s; those values
include network and hosting variation and are not the before/after comparison.
Render Free cold starts remain a hosting constraint.

## Confirmed defects and fixes

- Restore left files deleted in the authoritative snapshot on stale instances.
  The restored upload tree now removes files absent from the validated manifest.
- Malformed media could write a valid prefix before rejecting an unsafe suffix.
  The entire authenticated manifest is validated before writing any file.
- Duplicate and file/directory-conflicting paths now fail explicitly; symbolic
  links in existing uploads, including the uploads root itself, fail before
  reading or writing media.
- Missing or corrupt media now fails before importing the associated SQL.
  Existing current database/media snapshot format is unchanged.
- A near-capacity restore failed with PHP's default 128 MiB memory limit.
  Media compression and decompression now stream through size-limited temporary
  files, and restored files are copied in 64 KiB chunks. Recovery stages media
  before decrypting SQL. The regression archives 60 MiB at 2 MiB peak, then
  stages the archive while holding a 64 MiB SQL buffer at 66 MiB peak.
- Russian output translation changed `document.body.className` into
  `classИмя`, causing JavaScript errors. Script/style/JSON blocks are preserved.
- Dynamic mobile menu/filter labels now follow the selected language. Missing
  home category/footer copy is translated.
- Russian About heading caused horizontal overflow at 320 px on the existing
  live site. Its flex sizing and small-screen font sizing are corrected.
- Empty catalog searches now provide a browse-all link; reset removes the
  search and stale pagination as well as selected filters.

## Recovery evidence

A disposable real WordPress/WooCommerce/MariaDB donor created a fictional COD
order, private contact, subscriber and new uploaded WebP with WordPress image
sizes. An encrypted snapshot was saved to a temporary **local** bare Git repo.
The entire donor container and its ephemeral database were removed. A separate
new container restored the same order ID/status/items/total, private records,
attachment ID, original file hash and generated image sizes.

Missing and corrupted media snapshots were independently refused, with the
new container's database unchanged. The integration check also confirmed that
an SQL script that changes an option and then fails restores the previous
database and live media tree. Twelve isolated media regression cases, symlink
refusal before SQL import, file/directory collisions replaced from a staged
tree, and the memory check passed. No production recovery repository, real
customer data, payment gateway or live permissions were changed.

Reproduction instructions: `tests/README.md`. This remains small-demo recovery,
not an unlimited backup service or a permanent production database. Retention
still does not guarantee physical erasure of old unreachable Git objects.

## Storefront and review

43 browser checks passed across English/Russian and 320/390/1440 px, including
native cart and fictional COD checkout; no JavaScript errors were observed.
The final deployment image built successfully. Seven review lenses found no
confirmed new defects.

Recovery now validates and writes a complete media tree to a private staging
directory before decrypting and importing SQL, then swaps that directory into
place and removes the old tree. Before importing SQL, recovery saves the
existing database to a private temporary dump (up to 64 MiB). Import or media
swap failure triggers a compensating restore of the previous dump and removes
newly created tables. MariaDB import itself is not transactional; if the
compensating restore also fails, startup stays blocked and the private rollback
file is retained for manual recovery.
An existing persistent disk with the older flat uploads layout is copied into
the new swappable directory at startup when that directory is still empty. If
the process stops between directory renames, startup restores the retained old
uploads tree before creating an empty directory.

## Publication

The original performance and recovery audit commit `81cc8eb` is live on Render
from deployment `dep-db753mss728c73dfhm4g`. The staged-media recovery follow-up
is commit `8d916f4`, pushed to the existing PR #5 and its Render-linked branch
`fix/performance-recovery-audit`. After the push, the public home, shop, about,
journal, contact, FAQ, Russian shop, and sitemap endpoints all returned HTTP
200. Render's authenticated deployment details were not available in this
environment, so the exact deployment ID and deployed commit were not confirmed.
