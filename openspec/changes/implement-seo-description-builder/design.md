## Context
The description builder creates structured, SEO-optimized content in four blocks and injects contextually relevant phrases and long-tail keywords. It depends on the keyword distribution output and the FAQ template configuration.

## Goals / Non-Goals
- Goals: Produce deterministic, structured descriptions; inject context phrases; generate long-tail keywords; validate output length and keyword presence.
- Non-Goals: AI-generated free-form descriptions or UI/dashboard work.

## Decisions
- Keep block structure deterministic with minimum/maximum word counts.
- Inject context phrases only when not already present to avoid repetition.
- Long-tail generation derives from title and simple category context rules.

## Risks / Trade-offs
- Overlong text due to injections → mitigated by word-count constraints.
- Sparse keyword coverage → mitigated by using distributed keyword lists.

## Migration Plan
- Update services and tests without changing public endpoints.
- Validate outputs against tests and adjust limits if necessary.

## Open Questions
- Should block word-count ranges be configurable per category?
