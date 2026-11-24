<!-- obx:generated source=rules/principles.md -->


Portable engineering principles. Each holds in any repository, on any stack.

1. **Errors flow up; silence needs a justification.** Catch only what you can handle, log what you catch,
   and let the rest propagate. A broad catch is allowed only at a named stability boundary.
2. **Assert the transmitted or consumed value, not the persisted one.** A test proves behaviour only
   when it checks what the consumer actually reads or receives.
3. **One seam per question, for writes and for reads.** Each fact has a single resolver; callers go
   through it instead of re-deriving the answer.
4. **Every displayed value names its source.** When a value is derived, merged or inherited, show
   where it came from.
5. **Name by capability, not provider.** Types, tables and settings describe what a thing does; the
   vendor is a value, not a name.
6. **Credentials resolve from the bound scope, never a broad lookup.** Use the binding the request
   carries; "first match" or "any active" lookups leak across tenants and workspaces.
7. **Budgets ratchet down, never up.** A measured limit may be tightened; raising it needs an explicit,
   reviewed decision.
8. **Separate destructive schema changes from their cutover deploy.** Stop reading a field in one
   deploy; drop it in a later one.
