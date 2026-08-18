import Foundation
import SensitiveContentAnalysis

// MARK: - NSFWChecker.* bridge functions
//
// PHP calls these via `nativephp_call('NSFWChecker.CheckImage', jsonPayload)`.
// Registration is driven by the plugin manifest's `bridge_functions` block.

/// Namespace for sensitive-content bridge calls.
enum NSFWCheckerFunctions {

    /// `NSFWChecker.CheckImage` — run Apple's on-device sensitive content
    /// analysis over an image file and report whether it is sensitive.
    ///
    /// Returns `available: false` rather than throwing when the device cannot
    /// perform the analysis, so callers can distinguish "checked, it is clean"
    /// from "could not check".
    final class CheckImage: BridgeFunction {

        /// How long to wait for the analyzer before giving up.
        private let timeout: DispatchTimeInterval = .seconds(15)

        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let imagePath = parameters["imagePath"] as? String, !imagePath.isEmpty else {
                throw BridgeError.invalidParameters("Missing required parameter 'imagePath'")
            }

            guard FileManager.default.fileExists(atPath: imagePath) else {
                throw BridgeError.invalidParameters("Image file not found: \(imagePath)")
            }

            guard #available(iOS 17.0, *) else {
                return Self.unavailable("os_unsupported")
            }

            let analyzer = SCSensitivityAnalyzer()

            // .disabled means the user has not turned on Sensitive Content
            // Warning (Settings > Privacy & Security), or the device/policy
            // does not allow it. There is no result to be had in that case.
            guard analyzer.analysisPolicy != .disabled else {
                return Self.unavailable("policy_disabled")
            }

            let url = URL(fileURLWithPath: imagePath)

            var isSensitive = false
            var failure: Error?
            let semaphore = DispatchSemaphore(value: 0)

            analyzer.analyzeImage(at: url) { analysis, error in
                if let error {
                    failure = error
                } else {
                    isSensitive = analysis?.isSensitive ?? false
                }
                semaphore.signal()
            }

            guard semaphore.wait(timeout: .now() + timeout) == .success else {
                throw BridgeError.executionFailed("Sensitive content analysis timed out")
            }

            if let failure {
                throw BridgeError.executionFailed(failure.localizedDescription)
            }

            return [
                "available": true,
                "isSensitive": isSensitive,
            ]
        }

        private static func unavailable(_ reason: String) -> [String: Any] {
            return [
                "available": false,
                "isSensitive": false,
                "reason": reason,
            ]
        }
    }
}
