# Upcoming Events Module

Display upcoming events from The Events Calendar with a simple, customizable shortcode. No Azure credentials required!

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| TEC Integration | Displays events from The Events Calendar |
| No Credentials | Works without Azure setup |
| Customizable | Layout, filtering, and styling options |
| Performance | Efficient PHP-based queries |

---

## 📦 Requirements

- **The Events Calendar** plugin must be installed and activated
- No Azure credentials needed!

---

## ⚙️ Configuration

### Location
**Azure Plugin → Upcoming Events**

This page provides:
- Shortcode documentation
- Live preview
- Available TEC categories reference

### No Setup Required

This module works immediately after enabling. Simply use the shortcode on any page or post.

---

## 📝 Shortcode

### Basic Usage

```
[up-next]
```

Shows this week and next week's events in a single column.

### Full Syntax

```
[up-next 
    current-week="true" 
    next-week="true" 
    columns="1" 
    exclude-categories="Private,Staff Only"
    week-start="monday"
    show-time="true"
    link-titles="true"
    show-empty="true"
    empty-message="No upcoming events."
    this-week-title="This Week"
    next-week-title="Next Week"
]
```

---

## 📊 Attributes

### Week Display

| Attribute | Default | Description |
|-----------|---------|-------------|
| `current-week` | `"true"` | Show this week's events |
| `next-week` | `"true"` | Show next week's events |
| `week-start` | `"monday"` | Week starts on "monday" or "sunday" |

### Layout

| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | `"1"` | Layout columns (1, 2, or 3) |
| `show-empty` | `"true"` | Show sections even if no events |

### Filtering

| Attribute | Default | Description |
|-----------|---------|-------------|
| `exclude-categories` | `""` | TEC categories to exclude (comma-separated) |

### Content

| Attribute | Default | Description |
|-----------|---------|-------------|
| `show-time` | `"true"` | Display event times |
| `link-titles` | `"true"` | Make titles clickable links |
| `empty-message` | `"No upcoming events."` | Message when no events |

### Headings

| Attribute | Default | Description |
|-----------|---------|-------------|
| `this-week-title` | `"This Week"` | Heading for current week |
| `next-week-title` | `"Next Week"` | Heading for next week |

---

## 💡 Examples

### Two-Column Layout

```
[up-next columns="2"]
```

Shows this week and next week side by side.

### This Week Only

```
[up-next next-week="false"]
```

Shows only current week's events.

### Exclude Private Events

```
[up-next exclude-categories="Private,Staff Meetings"]
```

Hides events in specified categories.

### Custom Headings

```
[up-next 
    this-week-title="Happening Now" 
    next-week-title="Coming Up"
]
```

### Minimal Display

```
[up-next 
    show-time="false" 
    show-empty="false"
]
```

No times, hides empty weeks.

### Sunday-Start Week

```
[up-next week-start="sunday"]
```

Week starts on Sunday instead of Monday.

---

## 🎨 Styling

### Default Classes

```css
/* Container */
.upcoming-events { }

/* Column layouts */
.upcoming-columns-2 { }
.upcoming-columns-3 { }

/* Week sections */
.upcoming-week { }
.upcoming-current-week { }
.upcoming-next-week { }

/* Event list */
.upcoming-list { }
.upcoming-event { }

/* Event parts */
.upcoming-date { }
.upcoming-separator { }
.upcoming-title { }

/* Empty state */
.upcoming-empty { }
```

### Custom Styling Example

```css
/* Custom styling */
.upcoming-events {
    font-family: 'Georgia', serif;
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
}

.upcoming-week h3 {
    color: #0073aa;
    border-bottom: 2px solid #0073aa;
}

.upcoming-event {
    padding: 8px 0;
    border-bottom: 1px dotted #ddd;
}

.upcoming-date {
    font-weight: bold;
    color: #333;
}

a.upcoming-title:hover {
    color: #005a87;
}
```

### Responsive Behavior

Multi-column layouts automatically stack on mobile (< 768px width).

---

## 🔍 How It Works

### Query Logic

1. Calculates week boundaries based on `week-start`
2. Queries TEC events in date range
3. Filters by excluded categories
4. Sorts by start date
5. Renders HTML output

### Performance

- Uses WordPress `WP_Query` for efficiency
- No external API calls
- Results can be cached by page caching plugins

---

## 🔧 Troubleshooting

### "No events showing"

1. Verify The Events Calendar is installed and active
2. Check that events exist in the current/next week
3. Ensure events are published (not draft)
4. Check excluded categories aren't hiding events

### "Wrong week showing"

1. Verify `week-start` setting
2. Check server timezone settings
3. Review WordPress timezone (Settings → General)

### "Styling not applied"

1. Clear any caching plugins
2. Check for CSS conflicts with theme
3. Use browser inspector to verify classes

---

## ➡️ Related

- **[Calendar Sync Module](Calendar-Sync-Module)** - Sync events from Outlook
- **[Calendar Embed Module](Calendar-Embed-Module)** - Embed Outlook calendars
- **[Classes Module](Classes-Module)** - Class products with events


