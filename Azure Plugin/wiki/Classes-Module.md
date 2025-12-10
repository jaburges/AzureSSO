# Classes Module (WooCommerce)

Create class-based products in WooCommerce that automatically generate The Events Calendar events. Supports fixed and variable pricing with a commit-to-buy flow.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| Custom Product Type | "Class" product type in WooCommerce |
| TEC Integration | Auto-generates calendar events |
| Variable Pricing | Price based on enrollment count |
| Commit-to-Buy | $0 checkout with later payment |
| Provider Management | Track class providers/instructors |

---

## 📦 Requirements

- **WooCommerce** plugin installed and activated
- **The Events Calendar** plugin installed and activated
- No Azure credentials required!

---

## ⚙️ Configuration

### Location
**Azure Plugin → Classes**

### Enable the Module

1. Go to **Azure Plugin** dashboard
2. Find the **Classes** module card
3. Toggle to enable
4. Click **Save Settings**

---

## 🛍️ Creating a Class Product

### Step 1: New Product

1. Go to **Products → Add New**
2. In the **Product data** dropdown, select **Class**
3. New tabs appear: Class Schedule, Class Details, Class Pricing

### Step 2: Class Schedule Tab

| Field | Description | Required |
|-------|-------------|----------|
| **Start Date** | First class date | ✅ |
| **Season** | Fall/Winter/Spring/Summer (auto-calculated) | ✅ |
| **Recurrence** | Daily, Weekly, 2-Weekly, Monthly | ✅ |
| **Number of Occurrences** | How many sessions | ✅ |
| **Start Time** | Class start time | ✅ |
| **Duration** | Length in minutes | ✅ |

### Step 3: Class Details Tab

| Field | Description | Required |
|-------|-------------|----------|
| **Class Provider** | Company/instructor | ✅ |
| **Venue** | TEC Venue location | ✅ |
| **Chaperone** | WordPress user or invite email | |

### Step 4: Class Pricing Tab

**Fixed Pricing:**
| Field | Description |
|-------|-------------|
| **Regular Price** | Standard class price |
| **Sale Price** | Optional discounted price |
| **Available Spots** | Maximum enrollments |
| **Allow Waitlist** | Enable backorders |
| **Tax Status** | Taxable, shipping only, none |
| **Tax Class** | Standard or custom |

**Variable Pricing:** (check "Variable Pricing" box)
| Field | Description |
|-------|-------------|
| **Minimum Attendees** | Minimum for class to run |
| **Price at Minimum** | Price if minimum enrolled |
| **Maximum Attendees** | Maximum capacity |
| **Price at Maximum** | Price if maximum enrolled |

---

## 📅 Automatic Event Creation

### What Happens on Save

When you save a Class product:

1. **TEC Category Created:**
   - Name format: `{Class Name} - {Year} - {Season}`
   - Example: "Chess Club - 2025 - Fall"
   - Child of "Enrichment" parent category

2. **Events Generated:**
   - One event per occurrence
   - Linked to the product
   - Same status as product (draft/publish)

3. **Venue Assigned:**
   - Uses selected TEC Venue
   - Address shows on product page

### Event Editing

- Events can be individually edited in TEC
- Change dates for holidays/conflicts
- Modified events marked as "Modified"
- Original schedule preserved

---

## 💰 Pricing Models

### Fixed Pricing

Standard WooCommerce pricing:
- Set regular/sale price
- Customer pays at checkout
- Order completes normally

### Variable Pricing

Price decreases as more people enroll:

1. **Customer View:**
   - Sees "Likely Price: $X - $Y"
   - Current estimate based on commitments

2. **Checkout:**
   - Adds to cart at $0
   - Completes "commitment" checkout
   - Order status: "Committed"

3. **Admin Finalization:**
   - Sets final price when enrollment closes
   - Triggers payment request emails
   - Order status: "Awaiting Payment"

4. **Customer Payment:**
   - Receives email with payment link
   - Pays final amount
   - Order status: "Completed"

---

## 👥 Provider Management

### Access Provider Management
**Products → Class Providers** (or via Classes admin page)

### Provider Fields

| Field | Description |
|-------|-------------|
| **Company Name** | Provider organization |
| **Contact Person** | Primary contact |
| **Emergency Contact** | Phone/email for emergencies |
| **Website** | Provider website URL |

### Using Providers

1. Create provider in taxonomy
2. Select when creating class
3. Provider info displays on product

---

## 📝 Shortcodes

### Class Schedule

```
[class_schedule product_id="123"]
```

Displays the schedule with:
- Session list with dates/times
- Venue information
- Calendar subscription link
- Modified/cancelled session indicators

**Attributes:**
| Attribute | Description |
|-----------|-------------|
| `product_id` | Class product ID |
| `format` | list (default), table, calendar |

### Class Pricing

```
[class_pricing product_id="123"]
```

For variable pricing, shows:
- Price range
- Current commitment count
- Spots remaining

---

## 🗓️ Product Page Features

### Automatic Content

Class products automatically display:

1. **Schedule Section:**
   - Two-column layout
   - Venue info on left
   - Calendar subscribe on right
   - Session list below

2. **Session Numbers:**
   - Clickable links to TEC events
   - Shows date and time
   - Cancelled sessions marked

3. **Calendar Subscription:**
   - Add to Google Calendar
   - Add to Apple Calendar
   - Add to Outlook

4. **Description:**
   - Full product description
   - Displays in dedicated section

---

## 🔧 Troubleshooting

### "Events not creating"

1. Verify TEC is active
2. Check schedule fields are complete
3. Verify dates are in the future
4. Check product is saved (not auto-draft)

### "Add to Cart not showing"

1. Ensure product is published
2. Verify stock/spots available
3. Check price is set (or variable pricing enabled)
4. Clear caches

### "Provider taxonomy error"

1. Go to **Azure Plugin → Classes**
2. Ensure module is enabled
3. Flush permalinks (Settings → Permalinks → Save)

### "Variable pricing showing $0"

This is expected! Variable pricing uses commit-to-buy flow:
1. Customer checks out at $0
2. Admin sets final price later
3. Customer pays actual amount

---

## 📊 Order Flow

### Fixed Price

```
Add to Cart → Checkout → Payment → Complete
```

### Variable Price

```
Add to Cart ($0) → Commit Checkout → [Awaiting Enrollment Close]
                                    ↓
                             Admin Sets Final Price
                                    ↓
                           Payment Request Email → Customer Pays → Complete
```

---

## ➡️ Related

- **[Calendar Sync Module](Calendar-Sync-Module)** - Sync events to Outlook
- **[Upcoming Events Module](Upcoming-Events-Module)** - Display events
- **[Troubleshooting](Troubleshooting)** - Common issues


