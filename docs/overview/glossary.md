# Glossary

[Docs Hub](../README.md) / [Overview](index.md) / **Glossary**

| Term                    | Definition                                                                                                                                                         |
|-------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Signal**              | A structured data array produced by an analyzer from raw profiler data. Contains metrics, origin classification, and grouping info.                                |
| **Opportunity**         | A scored, actionable optimization recommendation produced by the AdvisorEngine from signals. Carries impact/effort/confidence, ROI, risk, evidence, and AI prompt. |
| **Origin**              | Classification of a signal source: `app` (your code), `infra` (framework/library), or `profiler` (profiler overhead). Only `app` signals generate opportunities.   |
| **Fingerprint**         | `md5(code + "\|" + evidence)` — unique identifier for deduplication. Two opportunities with the same fingerprint are considered duplicates.                        |
| **ROI**                 | Return on Investment = `impact * confidence / effort`. Used to sort opportunities (highest first).                                                                 |
| **Quick Win**           | An opportunity where `effort <= 2` AND `confidence >= 4`. Low-hanging fruit that is easy to implement with high certainty.                                         |
| **High Impact**         | An opportunity where `impact >= 4`. Significant performance improvement expected.                                                                                  |
| **Detection Rule**      | One of 14 `detect*` methods in `AdvisorEngine` that evaluates signals against thresholds to produce opportunities.                                                 |
| **Late Data Collector** | A data collector implementing `LateDataCollectorInterface`. Runs after all normal collectors, ensuring profiler data is complete before analysis.                  |
| **Optimization Score**  | A 0-100 score starting at 100, reduced by `impact * confidence * 0.5` per opportunity. Lower scores indicate more optimization potential.                          |
| **Evidence Ref**        | A string reference (SQL pattern, pool name, template name, listener class, endpoint fingerprint) linking an opportunity to the triggering signal.                  |
| **AI Prompt**           | A structured text block in each opportunity containing problem description, evidence, recommended actions, and safety notes — ready for AI assistant use.          |

---

[&larr; Tech Stack](tech-stack.md) | [Next: Components &rarr;](../components/index.md)
