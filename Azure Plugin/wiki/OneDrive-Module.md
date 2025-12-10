# OneDrive Media Module

Browse and insert OneDrive files directly into WordPress posts and pages.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| File Browser | Navigate OneDrive folders |
| Media Integration | Insert files into content |
| Large File Support | Handles files > 4MB |
| Upload to OneDrive | Upload from WordPress |

---

## ⚙️ Configuration

### Location
**Azure Plugin → OneDrive Media**

### Prerequisites
- Azure App Registration with `Files.Read.All` or `Files.ReadWrite.All` permission

### Setup

1. Enable the module
2. Click **Authenticate**
3. Complete Microsoft sign-in
4. Grant file access permissions

---

## 📁 Using OneDrive Files

### In Post Editor

1. Click **Add Media** button
2. Select **OneDrive** tab
3. Navigate to your file
4. Click to select
5. Insert into content

### Supported File Types

| Type | Action |
|------|--------|
| Images | Embed directly |
| PDFs | Link or embed |
| Documents | Link to OneDrive |
| Videos | Embed player |

---

## 🔧 Troubleshooting

### "No files showing"

1. Verify authentication completed
2. Check Files.Read.All permission
3. Ensure files exist in OneDrive

### "Large file fails"

1. Check PHP upload limits
2. Files use resumable upload
3. Try refreshing and retry

---

## ➡️ Related

- **[Azure App Registration](Azure-App-Registration)** - Set up permissions


