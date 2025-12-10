# Calendar Embed Module

Embed Outlook calendars on your WordPress site using shortcodes. Supports shared mailboxes and multiple calendar views.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| Embed Calendars | Display Outlook calendars on any page |
| Shared Mailbox | Access shared organization calendars |
| Multiple Views | Month, week, day, and list views |
| Customizable | Colors, timezone, and layout options |
| Caching | Performance optimized with caching |

---

## ⚙️ Configuration

### Location
**Azure Plugin → Calendar Embed**

### Prerequisites
- Azure App Registration with `Calendars.Read` permission
- Delegate access to shared mailbox (if using shared)

### Authentication Setup

1. **Your M365 Account:** The account you'll authenticate with
2. **Shared Mailbox Email:** The mailbox containing calendars
3. Click **Save Settings**
4. Click **Authenticate Calendar**
5. Complete Microsoft sign-in
6. Grant calendar permissions

### Calendar Selection

After authentication:
1. Available calendars appear in a list
2. Toggle **Enable for embedding** for each calendar
3. Set timezone per calendar
4. Copy the calendar ID for shortcodes
5. Click **Save Calendar Settings**

### Display Settings

| Setting | Description |
|---------|-------------|
| **Default Timezone** | Timezone for event display |
| **Default View** | Initial calendar view |
| **Color Theme** | Calendar color scheme |
| **Cache Duration** | How long to cache events |
| **Max Events** | Maximum events per request |

---

## 📝 Shortcodes

### Full Calendar Display

```
[azure_calendar email="calendar@org.net" id="CALENDAR_ID"]
```

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `email` | (required) | Shared mailbox email |
| `id` | (required) | Calendar ID from settings |
| `view` | "month" | month, week, day, list |
| `height` | "600px" | Calendar height |
| `width` | "100%" | Calendar width |
| `timezone` | Settings default | Display timezone |
| `max_events` | 100 | Maximum events to show |
| `show_weekends` | "true" | Show weekend days |

**Examples:**
```
[azure_calendar email="calendar@wilderptsa.net" id="AAA..." view="month" height="500px"]

[azure_calendar email="events@company.com" id="BBB..." view="week" show_weekends="false"]

[azure_calendar email="calendar@org.net" id="CCC..." view="list" max_events="20"]
```

### Events List

```
[azure_calendar_events email="calendar@org.net" id="CALENDAR_ID"]
```

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `email` | (required) | Shared mailbox email |
| `id` | (required) | Calendar ID |
| `limit` | 10 | Number of events |
| `format` | "list" | list, grid, compact |
| `upcoming_only` | "true" | Only future events |
| `show_dates` | "true" | Display dates |
| `show_times` | "true" | Display times |
| `show_location` | "true" | Display locations |
| `days_ahead` | 30 | How far ahead to look |

**Examples:**
```
[azure_calendar_events email="calendar@org.net" id="AAA..." limit="5" format="compact"]

[azure_calendar_events email="events@company.com" id="BBB..." days_ahead="90" show_location="false"]
```

---

## 🔍 Finding Calendar IDs

### From Plugin Settings

1. Go to **Azure Plugin → Calendar Embed**
2. After authentication, calendars are listed
3. Each calendar shows its ID
4. Click to copy

### Calendar ID Format

Calendar IDs look like:
```
AAMkADQ2ZDQ2MzU2LTY3ZDAtNDg5...
```

---

## 🎨 Customization

### Color Themes

Available themes:
- **Blue** (default)
- **Green**
- **Red**
- **Purple**
- **Orange**
- **Gray**

Set via shortcode or default settings.

### Custom CSS

Add custom styles to your theme:

```css
/* Calendar container */
.azure-calendar-container {
    border: 1px solid #ddd;
    border-radius: 8px;
}

/* Event styling */
.azure-calendar-event {
    background: #0078d4;
    color: white;
}

/* List view */
.azure-events-list li {
    padding: 10px;
    border-bottom: 1px solid #eee;
}
```

---

## 🔐 Shared Mailbox Setup

### Why Shared Mailbox?

Shared mailboxes allow multiple users to access the same calendars without sharing personal credentials.

### Setting Up Access

1. **In Microsoft 365 Admin:**
   - Go to admin.microsoft.com
   - Navigate to **Groups → Shared mailboxes**
   - Select your shared mailbox
   - Click **Edit** under "Read and manage"
   - Add users who need access

2. **Verify Access:**
   - Open Outlook
   - Add the shared mailbox
   - Confirm you can see calendars

### Authentication Flow

1. User authenticates with their personal M365 account
2. Plugin accesses shared mailbox calendars via delegation
3. No shared credentials needed

---

## 🔄 Caching

### How Caching Works

Events are cached to improve performance:
1. First request fetches from Microsoft
2. Subsequent requests use cache
3. Cache expires based on duration setting
4. Manual refresh available

### Cache Duration

Recommended settings:
| Use Case | Duration |
|----------|----------|
| Active calendars | 5-15 minutes |
| Stable schedules | 1 hour |
| Rarely changed | 4+ hours |

### Clear Cache

1. Go to **Azure Plugin → Calendar Embed**
2. Click **Clear Cache**
3. Next request will fetch fresh data

---

## 🔧 Troubleshooting

### "No calendars found"

1. Verify authentication is complete
2. Check you have delegate access to shared mailbox
3. Click **Refresh Calendars**
4. Check Calendars.Read permission is granted

### "Events not showing"

1. Verify calendar ID is correct
2. Check timezone settings
3. Confirm events exist in the date range
4. Clear cache and reload

### "Wrong timezone"

1. Set timezone in shortcode: `timezone="America/Los_Angeles"`
2. Or update default in settings
3. Ensure Outlook events have correct timezone

### "Authentication expired"

1. Token expires after some time
2. Click **Re-authenticate**
3. Complete sign-in again

---

## ➡️ Related

- **[Calendar Sync Module](Calendar-Sync-Module)** - Sync to The Events Calendar
- **[Upcoming Events Module](Upcoming-Events-Module)** - Display TEC events
- **[Troubleshooting](Troubleshooting)** - Common issues


