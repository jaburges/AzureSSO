# PTA Tools Plugin — To-Do

## Planned Modules

### Page Holding
**Priority:** Medium
**Status:** Planned

A module that allows pages to be put into a "holding" state with a full-viewport overlay banner, without changing the publish status. Ideal for seasonal event pages (e.g. Art Night, Carnival) that become outdated after the event ends.

**Key features:**
- Meta-based toggle per page — page stays Published (no broken links, menus, or SEO impact)
- Full-viewport sticky banner overlay with customizable message (e.g. "This event has been and gone this year, but we'll be back with more fun next year!")
- Page content remains visible below the banner if the user scrolls
- Configurable banner background color/image, CTA button text and link
- Default message template in module settings, with per-page override
- Optional: auto-enable holding mode after a configured end date
- Dashboard widget or list view showing all pages currently in holding mode
- Bulk toggle from PTA Tools admin

**Implementation notes:**
- New module toggle "Page Holding" on the main PTA Tools dashboard (default off)
- Per-page meta box in the WordPress editor with toggle + message fields
- Frontend: lightweight CSS overlay (position sticky, 100vh), no JS dependencies
- No new database tables needed — uses post meta only

---

## Backlog / Ideas

_Add future module ideas and improvements here._
