# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-18

First tagged release.

### Added
- `NSFWChecker.CheckImage` bridge function for iOS and Android.
- iOS implementation backed by Apple's SensitiveContentAnalysis framework
  (`SCSensitivityAnalyzer`), requiring iOS 17 and the
  `com.apple.developer.sensitivecontentanalysis.client` entitlement, which the
  plugin manifest declares.
- Android implementation backed by ML Kit image labelling. This is a weak
  heuristic over apparel and exposed-skin labels, not a nudity classifier; it
  reports `heuristic: true` so callers can weight it accordingly.
- `checkImage()` distinguishes "analysed, clean" from "could not analyse" via
  an `available` flag; `isNSFW()` fails closed by default.
- Test suite covering the PHP bridge contract against `Native\Mobile\Testing\FakeBridge`.
