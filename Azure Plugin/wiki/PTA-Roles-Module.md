# PTA Roles Module

Manage organizational roles, departments, and volunteer assignments with optional Microsoft 365 Groups synchronization.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| Department Management | Organize by functional areas |
| Role Tracking | Define positions and requirements |
| User Assignments | Assign members to roles |
| O365 Groups Sync | Sync with Microsoft 365 Groups |
| Audit Logging | Track all changes |

---

## ⚙️ Configuration

### Location
**Azure Plugin → PTA Roles**

### Initial Setup

1. Enable the PTA module
2. Click **Reimport Default Tables**
   - Creates database tables
   - Imports sample departments/roles
3. Customize as needed

---

## 🏢 Department Management

### Creating Departments

1. Go to **PTA Roles** tab
2. Click **Add Department**
3. Fill in:
   - Department name
   - Description
   - VP assignment (optional)
4. Click **Save**

### Department Fields

| Field | Description |
|-------|-------------|
| **Name** | Department name (e.g., "Communications") |
| **Description** | Purpose of department |
| **VP** | Vice President/Lead |
| **Order** | Display order |

---

## 👔 Role Management

### Creating Roles

1. Select a department
2. Click **Add Role**
3. Fill in:
   - Role name
   - Description
   - Max occupants
   - Requirements
4. Click **Save**

### Role Fields

| Field | Description |
|-------|-------------|
| **Name** | Role title (e.g., "Newsletter Editor") |
| **Department** | Parent department |
| **Max Occupants** | Limit (0 = unlimited) |
| **Description** | Role responsibilities |
| **Requirements** | Skills/availability needed |

---

## 👥 User Assignments

### Assigning Users

1. Go to **PTA Roles**
2. Find the role
3. Click **Assign User**
4. Select WordPress user
5. Set as primary role (optional)
6. Click **Save**

### Assignment Options

| Option | Description |
|--------|-------------|
| **Primary Role** | User's main position |
| **Secondary Roles** | Additional responsibilities |
| **Start Date** | When assignment begins |
| **End Date** | When assignment ends |

---

## 🔄 O365 Groups Sync

### Location
**Azure Plugin → PTA Groups**

### Prerequisites
- Azure App Registration with `Group.ReadWrite.All` permission

### Setup

1. Click **Authenticate**
2. Complete Microsoft sign-in
3. Click **Fetch Groups**
4. Create mappings:
   - Map roles to groups
   - Map departments to groups
5. Click **Sync Now**

### Sync Behavior

- Users assigned to role → Added to mapped group
- Users removed from role → Removed from group
- Can be scheduled automatically

---

## 📝 Shortcodes

### Roles Directory

```
[pta-roles-directory]
```

Displays all roles with assignees.

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `department` | "all" | Filter by department |
| `columns` | "3" | Layout columns |
| `show_empty` | "true" | Show unfilled roles |

### Department Roles

```
[pta-department-roles department="communications"]
```

Shows roles in a specific department.

### Organization Chart

```
[pta-org-chart]
```

Visual organization hierarchy.

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `department` | "all" | Filter by department |
| `interactive` | "true" | Clickable nodes |

### Open Positions

```
[pta-open-positions]
```

Lists unfilled roles.

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | "10" | Max positions shown |
| `department` | "all" | Filter by department |

### Department VP

```
[pta-department-vp department="fundraising"]
```

Shows the VP for a department.

---

## 📊 Audit Log

All changes are logged:
- Role assignments
- User removals
- Role/department changes
- O365 sync operations

View at: **PTA Roles → Audit Log**

---

## 📥 Import/Export

### CSV Import

1. Prepare CSV with columns:
   - department, role, max_occupants, description
2. Go to **PTA Roles → Import**
3. Upload file
4. Review and confirm

### CSV Export

1. Go to **PTA Roles → Export**
2. Click **Download CSV**
3. Opens in Excel/Sheets

---

## 🔧 Troubleshooting

### "Table doesn't exist"

1. Go to **PTA Roles**
2. Click **Reimport Default Tables**
3. Tables are created automatically

### "O365 sync failing"

1. Verify Group.ReadWrite.All permission
2. Check admin consent is granted
3. Ensure groups exist in M365

---

## ➡️ Related

- **[SSO Module](SSO-Module)** - Azure AD authentication
- **[Azure App Registration](Azure-App-Registration)** - Permissions setup


