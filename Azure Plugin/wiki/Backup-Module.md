# Backup Module

Automatically back up your WordPress site to Azure Blob Storage with scheduled backups, restoration, and retention policies.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| Azure Blob Storage | Store backups in Microsoft cloud |
| Flexible Backup Types | Database, content, media, plugins, themes |
| Scheduled Backups | Hourly, daily, weekly, or monthly |
| Easy Restoration | Restore from any backup point |
| Retention Policies | Automatically delete old backups |

---

## ⚙️ Configuration

### Location
**Azure Plugin → Backup**

### Prerequisites

You need an **Azure Storage Account**:

1. Go to [Azure Portal](https://portal.azure.com)
2. Create a new **Storage Account**
3. Note the **Storage Account Name**
4. Get an **Access Key** from Settings → Access Keys

### Settings

#### Azure Storage Credentials
| Setting | Description |
|---------|-------------|
| **Storage Account Name** | Your Azure Storage account name |
| **Storage Access Key** | Primary or secondary access key |
| **Container Name** | Container for backups (auto-created) |

#### Backup Types
| Type | What's Included |
|------|-----------------|
| **Database** | All WordPress tables |
| **Content** | wp-content folder (excluding media) |
| **Media** | Uploads folder |
| **Plugins** | All installed plugins |
| **Themes** | All installed themes |

#### Schedule Settings
| Setting | Options |
|---------|---------|
| **Enable Scheduled Backups** | On/Off |
| **Frequency** | Hourly, Daily, Weekly, Monthly |
| **Time** | When to run (for daily+) |
| **Day** | Day of week/month (for weekly/monthly) |

#### Retention
| Setting | Description |
|---------|-------------|
| **Retention Days** | Days to keep backups (0 = forever) |
| **Max Backups** | Maximum number to keep |

---

## 🔄 Running Backups

### Manual Backup

1. Go to **Azure Plugin → Backup**
2. Select backup types
3. Click **Start Manual Backup**
4. Watch the progress bar

### Progress Tracking

The backup shows:
- Current phase (database, files, etc.)
- Files processed
- Size transferred
- Estimated time remaining

### Backup Status

| Status | Meaning |
|--------|---------|
| **Pending** | Queued, waiting to start |
| **Running** | Currently in progress |
| **Completed** | Successfully finished |
| **Failed** | Error occurred (check logs) |
| **Cancelled** | Manually cancelled |

---

## 📥 Restoration

### Restore from Backup

1. Go to **Azure Plugin → Backup**
2. Find the backup in **Recent Backup Jobs**
3. Click **Restore**
4. Confirm the restoration

> ⚠️ **Warning:** Restoration will overwrite current content!

### What Gets Restored

Each backup type restores independently:
- **Database** → Replaces all tables
- **Content** → Replaces wp-content (except uploads)
- **Media** → Replaces uploads folder
- **Plugins** → Replaces plugins folder
- **Themes** → Replaces themes folder

### Post-Restoration

After restoration:
1. Clear all caches
2. Verify site functionality
3. Check user access
4. Test critical features

---

## 📊 Azure Storage Setup

### Create Storage Account

1. Go to **Azure Portal → Storage accounts**
2. Click **+ Create**
3. Fill in:
   - **Subscription:** Your subscription
   - **Resource group:** Create or select
   - **Storage account name:** Unique name (lowercase, numbers only)
   - **Region:** Choose nearest
   - **Performance:** Standard
   - **Redundancy:** LRS (cheapest) or GRS (safer)

4. Click **Review + Create** → **Create**

### Get Access Key

1. Go to your Storage Account
2. Click **Settings → Access keys**
3. Click **Show** on key1
4. Copy the **Key** value

### Container Setup

The plugin will automatically create a container named `wordpress-backups` (or your specified name).

You can view backups:
1. Go to Storage Account
2. Click **Data storage → Containers**
3. Click your container
4. Browse backup files

---

## 💰 Cost Considerations

### Azure Storage Costs

Typical costs (as of 2024):
- **LRS Storage:** ~$0.018/GB/month
- **Data transfer:** First 5GB free, then ~$0.087/GB

### Cost Estimates

| Site Size | Backups/Month | Estimated Cost |
|-----------|---------------|----------------|
| Small (1GB) | Daily | ~$0.50/month |
| Medium (5GB) | Daily | ~$2.50/month |
| Large (20GB) | Daily | ~$10/month |

### Cost Optimization

1. Use **retention policies** to delete old backups
2. **Exclude media** if stored elsewhere (CDN, etc.)
3. Use **weekly** instead of daily for stable sites
4. Choose **LRS** redundancy for development sites

---

## 🔧 Troubleshooting

### "Backup stuck at X%"

1. Check PHP memory limit (256M+ recommended)
2. Increase max_execution_time (300+ seconds)
3. Very large sites may need to exclude some backup types

### "Storage connection failed"

1. Verify account name is correct (no .blob.core.windows.net)
2. Check access key is correct
3. Ensure container name has no special characters

### "Backup completed but files missing"

1. Check storage container in Azure Portal
2. Verify sufficient storage quota
3. Review logs for specific file errors

### Performance Tips

```php
// Add to wp-config.php for large sites
define('WP_MEMORY_LIMIT', '512M');
ini_set('max_execution_time', 600);
```

---

## 🔐 Security

### Access Key Security

- Access keys provide full storage access
- Store securely in WordPress
- Rotate keys periodically
- Consider using SAS tokens for limited access

### Backup Encryption

Backups are stored as-is in Azure Storage. For additional security:
- Enable **Azure Storage encryption** (default)
- Use **HTTPS** for all transfers (automatic)
- Consider **Azure Private Endpoints** for sensitive data

---

## ➡️ Related

- **[Azure App Registration](Azure-App-Registration)** - General Azure setup
- **[Troubleshooting](Troubleshooting)** - Common issues
- **[Advanced Configuration](Advanced-Configuration)** - Power user settings


