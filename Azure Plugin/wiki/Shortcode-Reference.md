# Shortcode Reference

Complete reference for all shortcodes provided by Microsoft WP.

---

## 📋 Quick Reference

| Shortcode | Module | Description |
|-----------|--------|-------------|
| `[azure_sso_login]` | SSO | Microsoft login button |
| `[azure_sso_logout]` | SSO | Logout button |
| `[azure_user_info]` | SSO | Display user information |
| `[azure_calendar]` | Calendar Embed | Full calendar display |
| `[azure_calendar_events]` | Calendar Embed | Event list |
| `[up-next]` | Upcoming Events | TEC events display |
| `[class_schedule]` | Classes | Class schedule |
| `[class_pricing]` | Classes | Class pricing info |
| `[azure_email_form]` | Email | Contact form |
| `[pta-roles-directory]` | PTA | Roles directory |
| `[pta-department-roles]` | PTA | Department roles |
| `[pta-org-chart]` | PTA | Organization chart |
| `[pta-open-positions]` | PTA | Open positions |
| `[pta-department-vp]` | PTA | Department VP |

---

## 🔐 SSO Module

### azure_sso_login

Displays a Microsoft sign-in button.

```
[azure_sso_login text="Sign in with Microsoft" redirect="/dashboard" class="my-class"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `text` | "Sign in with Microsoft" | Button text |
| `redirect` | Current page | URL after login |
| `class` | "" | CSS class(es) |

### azure_sso_logout

Displays a logout button.

```
[azure_sso_logout text="Sign out" redirect="/"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `text` | "Sign out" | Button text |
| `redirect` | Home page | URL after logout |

### azure_user_info

Displays information about logged-in user.

```
[azure_user_info field="display_name"]
[azure_user_info] <!-- All fields -->
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `field` | (all) | Specific field to show |
| `format` | "text" | Output format |

**Available fields:** `display_name`, `first_name`, `last_name`, `email`, `department`, `user_login`, `roles`

---

## 📅 Calendar Embed Module

### azure_calendar

Displays a full calendar.

```
[azure_calendar 
    email="calendar@org.net" 
    id="AAMkADQ2..." 
    view="month" 
    height="600px"
]
```

| Attribute | Default | Required | Description |
|-----------|---------|----------|-------------|
| `email` | - | ✅ | Shared mailbox email |
| `id` | - | ✅ | Calendar ID |
| `view` | "month" | | month, week, day, list |
| `height` | "600px" | | Calendar height |
| `width` | "100%" | | Calendar width |
| `timezone` | Settings | | Timezone |
| `max_events` | 100 | | Max events |
| `show_weekends` | "true" | | Show weekends |

### azure_calendar_events

Displays an event list.

```
[azure_calendar_events 
    email="calendar@org.net" 
    id="AAMkADQ2..." 
    limit="10"
]
```

| Attribute | Default | Required | Description |
|-----------|---------|----------|-------------|
| `email` | - | ✅ | Shared mailbox email |
| `id` | - | ✅ | Calendar ID |
| `limit` | 10 | | Number of events |
| `format` | "list" | | list, grid, compact |
| `upcoming_only` | "true" | | Future events only |
| `show_dates` | "true" | | Display dates |
| `show_times` | "true" | | Display times |
| `show_location` | "true" | | Display locations |
| `days_ahead` | 30 | | Days to look ahead |

---

## 🗓️ Upcoming Events Module

### up-next

Displays upcoming TEC events.

```
[up-next 
    columns="2"
    current-week="true" 
    next-week="true"
    exclude-categories="Private"
]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `current-week` | "true" | Show this week |
| `next-week` | "true" | Show next week |
| `columns` | "1" | Layout columns (1-3) |
| `exclude-categories` | "" | Categories to hide |
| `week-start` | "monday" | monday or sunday |
| `show-time` | "true" | Show event times |
| `link-titles` | "true" | Clickable titles |
| `show-empty` | "true" | Show empty weeks |
| `empty-message` | "No upcoming events." | Empty message |
| `this-week-title` | "This Week" | Current week heading |
| `next-week-title` | "Next Week" | Next week heading |

---

## 🛒 Classes Module

### class_schedule

Displays class schedule.

```
[class_schedule product_id="123" format="list"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `product_id` | - | Class product ID |
| `format` | "list" | list, table, calendar |

### class_pricing

Displays class pricing.

```
[class_pricing product_id="123"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `product_id` | - | Class product ID |

---

## 📧 Email Module

### azure_email_form

Displays a contact form.

```
[azure_email_form to="info@org.net" subject="Contact"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `to` | Admin email | Recipient |
| `subject` | "Contact Form" | Subject line |
| `success` | "Sent!" | Success message |
| `button` | "Send" | Button text |

---

## 👥 PTA Module

### pta-roles-directory

Displays roles directory.

```
[pta-roles-directory department="all" columns="3"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `department` | "all" | Filter by department |
| `columns` | "3" | Layout columns |
| `show_empty` | "true" | Show unfilled roles |

### pta-department-roles

Shows roles in a department.

```
[pta-department-roles department="communications" show_vp="true"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `department` | - | Department slug |
| `show_vp` | "true" | Show VP info |

### pta-org-chart

Displays organization chart.

```
[pta-org-chart department="all" interactive="true"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `department` | "all" | Filter by department |
| `interactive` | "true" | Clickable nodes |

### pta-open-positions

Lists unfilled positions.

```
[pta-open-positions limit="10" show_department="true"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | "10" | Max positions |
| `department` | "all" | Filter by department |
| `show_department` | "true" | Show department name |

### pta-department-vp

Shows department VP.

```
[pta-department-vp department="fundraising"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `department` | - | Department slug |

---

## 🎨 Styling

### Common CSS Classes

All shortcodes output with namespaced classes:

```css
/* SSO */
.azure-sso-login-button { }
.azure-sso-logout-button { }
.azure-user-info { }

/* Calendar */
.azure-calendar-container { }
.azure-calendar-event { }
.azure-events-list { }

/* Upcoming */
.upcoming-events { }
.upcoming-week { }
.upcoming-event { }

/* Classes */
.class-schedule { }
.class-pricing { }

/* PTA */
.pta-directory { }
.pta-org-chart { }
```

### Custom Styling Example

```css
/* Custom button styling */
.azure-sso-login-button {
    background: #0078d4;
    color: white;
    padding: 12px 24px;
    border-radius: 4px;
}

/* Custom calendar styling */
.azure-calendar-container {
    border: 2px solid #ddd;
    border-radius: 8px;
}
```

---

## ➡️ Related

- **[SSO Module](SSO-Module)** - SSO shortcode details
- **[Calendar Embed Module](Calendar-Embed-Module)** - Calendar shortcodes
- **[Upcoming Events Module](Upcoming-Events-Module)** - Upcoming shortcode
- **[Classes Module](Classes-Module)** - Class shortcodes
- **[PTA Roles Module](PTA-Roles-Module)** - PTA shortcodes


