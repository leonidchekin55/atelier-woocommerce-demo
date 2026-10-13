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
  links in existing uploads fail before media writes.
- Missing or corrupt media now fails before importing the associated SQL.
  Existing current database/media snapshot format is unchanged.
- A near-capacity restore failed with PHP's default 128 MiB memory limit.
  Bounded gzip expansion, offset/length manifests and 64 KiB file writes avoid
  copying every restored file into another in-memory collection. The regression
  restores 60 MiB plus 16 MiB of simulated other state at 82.9–94.9 MiB peak
  across local runs.
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
new container's database unchanged. Ten isolated media regression cases
and the near-capacity memory test passed. No production recovery repository,
real customer data, payment gateway or live permissions were changed.

Reproduction instructions: `tests/README.md`. This remains small-demo recovery,
not an unlimited backup service or a permanent production database. Retention
still does not guarantee physical erasure of old unreachable Git objects.

## Storefront and review

43 browser checks passed across English/Russian and 320/390/1440 px, including
native cart and fictional COD checkout; no JavaScript errors were observed.
The final deployment image built successfully. Seven review lenses found no
confirmed new defects.

Recovery remains atomic per file, not across SQL and all media. Filesystem
refusal after SQL import stops completion and keeps the applied marker unchanged.
The memory test does not cover simultaneous maximum-size SQL and media archives.

## Publication

These changes are local until explicitly approved for publication. The existing
Render demo is not claimed to contain this audit's fixes yet.
