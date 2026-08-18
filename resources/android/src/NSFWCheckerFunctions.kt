package com.codypchristian.nsfwchecker

import android.content.Context
import android.graphics.BitmapFactory
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.label.ImageLabeling
import com.google.mlkit.vision.label.defaults.ImageLabelerOptions
import com.nativephp.mobile.bridge.BridgeError
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * NSFWChecker.* bridge functions (Android).
 *
 * IMPORTANT: unlike iOS — which uses Apple's purpose-built
 * SensitiveContentAnalysis framework — Android has no free, on-device model
 * for nude / sexually explicit content. What is available in ML Kit is a
 * *general purpose* image labeller whose default label set contains no
 * explicit categories at all. This implementation therefore reports a weak
 * proxy signal derived from apparel and exposed-skin labels, and flags itself
 * as a heuristic so callers can weight it accordingly.
 *
 * Do not treat an Android result as equivalent to an iOS result.
 */
object NSFWCheckerFunctions {

    /**
     * Labels from ML Kit's default (Open Images derived) label set that
     * correlate with undress. These are proxies, not detections.
     */
    private val SUGGESTIVE_LABELS = setOf(
        "brassiere",
        "swimwear",
        "bikini",
        "lingerie",
        "undergarment",
        "underpants",
        "briefs",
        "abdomen",
        "navel",
        "barechested",
        "thigh",
        "trunk",
    )

    private const val TIMEOUT_SECONDS = 15L

    class CheckImage(private val context: Context) : BridgeFunction {

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val imagePath = (parameters["imagePath"] as? String)?.takeIf { it.isNotEmpty() }
                ?: throw BridgeError.InvalidParameters("Missing required parameter 'imagePath'")

            val threshold = (parameters["threshold"] as? Number)?.toDouble() ?: 0.7

            if (!File(imagePath).exists()) {
                throw BridgeError.InvalidParameters("Image file not found: $imagePath")
            }

            val bitmap = BitmapFactory.decodeFile(imagePath)
                ?: throw BridgeError.InvalidParameters("Could not decode image: $imagePath")

            val labeler = ImageLabeling.getClient(ImageLabelerOptions.DEFAULT_OPTIONS)
            val latch = CountDownLatch(1)

            var isSensitive = false
            val matched = mutableListOf<Map<String, Any>>()
            var failure: Exception? = null

            try {
                labeler.process(InputImage.fromBitmap(bitmap, 0))
                    .addOnSuccessListener { labels ->
                        for (label in labels) {
                            val text = label.text.lowercase()
                            val confidence = label.confidence.toDouble()

                            if (text in SUGGESTIVE_LABELS && confidence >= threshold) {
                                isSensitive = true
                                matched.add(mapOf("label" to label.text, "confidence" to confidence))
                            }
                        }
                        latch.countDown()
                    }
                    .addOnFailureListener { e ->
                        failure = e
                        latch.countDown()
                    }

                if (!latch.await(TIMEOUT_SECONDS, TimeUnit.SECONDS)) {
                    throw BridgeError.ExecutionFailed("ML Kit image labelling timed out")
                }
            } finally {
                labeler.close()
            }

            failure?.let {
                throw BridgeError.ExecutionFailed(it.message ?: "ML Kit image labelling failed")
            }

            return mapOf(
                "available" to true,
                "isSensitive" to isSensitive,
                "heuristic" to true,
                "matchedLabels" to matched,
            )
        }
    }
}
