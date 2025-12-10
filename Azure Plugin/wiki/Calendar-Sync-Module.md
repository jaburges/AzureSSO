# Calendar Sync Module (TEC Integration)

Synchronize Outlook calendars with The Events Calendar plugin. Create and manage TEC events from your Microsoft 365 calendars.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| One-way Sync | Outlook → The Events Calendar |
| Two-way Sync | Bidirectional synchronization |
| Category Mapping | Map Outlook calendars to TEC categories |
| Recurring Events | Support for recurring event patterns |
| Scheduled Sync | Automatic synchronization |

---

## 📦 Requirements

- **The Events Calendar** plugin must be installed and activated
- Azure App Registration with `Calendars.Read` (or `Calendars.ReadWrite` for two-way)

---

## ⚙️ Configuration

### Location
**Azure Plugin → Calendar Sync**

### Authentication

1. Enter your **Microsoft 365 email**
2. Enter **Shared Mailbox email** (if using)
3. Click **Authenticate**
4. Complete Microsoft sign-in
5. Grant calendar permissions

### Calendar Mapping

After authentication:

1. Click **Fetch Available Calendars**
2. For each calendar you want to sync:
   - Click **Add Mapping**
   - Select the Outlook calendar
   - Choose or create TEC category
   - Set sync direction
   - Configure as primary/additional
3. Click **Save Mapping**

### Mapping Options

| Setting | Description |
|---------|-------------|
| **Outlook Calendar** | Source calendar from M365 |
| **TEC Category** | Target category in TEC |
| **Sync Direction** | One-way or Two-way |
| **Primary Calendar** | Main calendar for this category |

### Sync Settings

| Setting | Description |
|---------|-------------|
| **Lookback Days** | How far back to sync (default: 30) |
| **Lookahead Days** | How far forward to sync (default: 365) |
| **Sync Frequency** | Manual, hourly, daily, weekly |
| **Conflict Resolution** | Outlook wins or TEC wins |
| **Recurring Events** | Enable/disable recurring sync |

---

## 🔄 Synchronization

### Manual Sync

1. Go to **Azure Plugin → Calendar Sync**
2. Click **Sync Now**
3. Watch progress and results

### Scheduled Sync

Configure automatic sync:
1. Set **Sync Frequency** (hourly, daily, weekly)
2. Save settings
3. Sync runs automatically via WordPress cron

### Sync Results

After each sync:
- **Created:** New events added to TEC
- **Updated:** Existing events modified
- **Skipped:** Unchanged events
- **Errors:** Problems encountered

---

## 📊 Category Mapping

### How It Works

1. Each Outlook calendar maps to a TEC category
2. Events sync to that category
3. Multiple calendars can map to one category

### Creating Categories

**From WordPress:**
1. Go to **Events → Event Categories**
2. Add new category
3. Use in calendar mapping

**During Mapping:**
1. Type new category name
2. It's created automatically

### Category Hierarchy

You can create hierarchical categories:
```
Events (parent)
├── Meetings
├── Classes
└── Social
```

---

## 🔁 Recurring Events

### Supported Patterns

| Pattern | Example |
|---------|---------|
| Daily | Every day, every 2 days |
| Weekly | Every Monday, Every Mon/Wed/Fri |
| Monthly | 1st of month, 2nd Tuesday |
| Yearly | January 1st |

### How Recurring Events Sync

1. Plugin reads recurrence pattern from Outlook
2. Creates individual TEC events for each occurrence
3. Links them via shared meta data

### Editing Recurring Events

In TEC:
- Edit individual occurrences
- Changes don't sync back to Outlook (unless two-way enabled)

In Outlook:
- Edit series or single occurrence
- Next sync updates TEC accordingly

---

## ⚡ Performance

### Large Calendars

For calendars with many events:
1. Reduce **Lookahead Days**
2. Use **hourly** instead of more frequent sync
3. Enable caching

### Sync Timeout

If syncs timeout:
```php
// Add to wp-config.php
define('WP_MEMORY_LIMIT', '512M');
ini_set('max_execution_time', 600);
```

---

## 🔧 Troubleshooting

### "No events syncing"

1. Verify calendar mapping exists
2. Check date range (lookback/lookahead)
3. Confirm events exist in Outlook
4. Check sync logs for errors

### "Duplicate events"

1. Events may have been manually created
2. Check for existing events in date range
3. Use "Outlook wins" conflict resolution

### "Recurring events not appearing"

1. Enable **Recurring Events** in settings
2. Check pattern is supported
3. Verify date range includes occurrences

### "Two-way sync not working"

1. Ensure `Calendars.ReadWrite` permission
2. Set sync direction to "Two-way"
3. Check TEC event has sync metadata

---

## 🔐 Conflict Resolution

### Outlook Wins

- Changes from Outlook overwrite TEC
- Best for Outlook as source of truth
- Simpler, less chance of conflicts

### TEC Wins

- Local TEC changes preserved
- Outlook changes only apply to new events
- Best when TEC is primary editing location

---

## ➡️ Related

- **[Calendar Embed Module](Calendar-Embed-Module)** - Embed calendars
- **[Classes Module](Classes-Module)** - Class products with TEC events
- **[Upcoming Events Module](Upcoming-Events-Module)** - Display events


