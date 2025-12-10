# Contributing Guide

Thank you for your interest in contributing to Microsoft WP! This guide will help you get started.

---

## 🤝 Ways to Contribute

### Report Bugs

Found a bug? Please [open an issue](https://github.com/jamieburgess/microsoft-wp/issues/new) with:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior
- Error messages (if any)

### Suggest Features

Have an idea? Open an issue with:
- Clear description of the feature
- Use case / why it's needed
- Possible implementation approach

### Submit Code

Ready to code? Follow the process below.

### Improve Documentation

Documentation improvements are always welcome:
- Fix typos
- Clarify confusing sections
- Add examples
- Translate to other languages

---

## 🔧 Development Setup

### Prerequisites

- PHP 7.4+
- WordPress development environment
- Git
- Composer (for dependencies)
- Node.js (for asset building)

### Clone the Repository

```bash
# Fork the repo first, then clone your fork
git clone https://github.com/YOUR-USERNAME/microsoft-wp.git

# Navigate to plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Link or move the cloned repo
ln -s /path/to/microsoft-wp azure-plugin
```

### Install Dependencies

```bash
cd microsoft-wp

# PHP dependencies (if any)
composer install

# Node dependencies (for JS/CSS building)
npm install
```

---

## 📝 Pull Request Process

### 1. Create a Branch

```bash
# Start from main branch
git checkout main
git pull origin main

# Create feature branch
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

### 2. Make Your Changes

- Write clean, documented code
- Follow existing code style
- Add comments for complex logic
- Test thoroughly

### 3. Commit Your Changes

```bash
# Stage changes
git add .

# Commit with clear message
git commit -m "Add feature: brief description"
# or
git commit -m "Fix: brief description of bug fixed"
```

### 4. Push and Create PR

```bash
# Push to your fork
git push origin feature/your-feature-name
```

Then:
1. Go to GitHub
2. Click "Compare & pull request"
3. Fill in the PR template
4. Submit for review

---

## 📋 Code Style Guidelines

### PHP

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable names
- Document functions with PHPDoc

```php
/**
 * Brief description.
 *
 * Longer description if needed.
 *
 * @since 2.0.0
 *
 * @param string $param Description.
 * @return bool Description.
 */
public function example_function( $param ) {
    // Code here
}
```

### JavaScript

- Use ES6+ features
- Avoid jQuery when possible
- Use meaningful names

```javascript
/**
 * Brief description.
 *
 * @param {string} param - Description.
 * @returns {boolean} Description.
 */
function exampleFunction(param) {
    // Code here
}
```

### CSS

- Use BEM naming convention
- Avoid !important
- Mobile-first responsive

```css
/* Block */
.azure-calendar { }

/* Element */
.azure-calendar__header { }

/* Modifier */
.azure-calendar--compact { }
```

---

## 🧪 Testing

### Manual Testing

Before submitting PR:
1. Test on clean WordPress install
2. Test with required plugins (TEC, WooCommerce)
3. Test with PHP 7.4 and 8.0+
4. Test edge cases

### Test Checklist

- [ ] Works with latest WordPress
- [ ] Works with required plugins
- [ ] No PHP errors/warnings
- [ ] No JavaScript console errors
- [ ] Mobile responsive (if UI changes)
- [ ] Backward compatible

---

## 📁 Project Structure

```
microsoft-wp/
├── admin/              # Admin page templates
├── css/                # Stylesheets
├── includes/           # PHP classes
│   ├── class-admin.php
│   ├── class-sso-auth.php
│   └── ...
├── js/                 # JavaScript files
├── templates/          # Output templates
├── wiki/               # Documentation
├── azure-plugin.php    # Main plugin file
└── README.md
```

### Key Files

| File | Purpose |
|------|---------|
| `azure-plugin.php` | Main plugin file, initialization |
| `includes/class-admin.php` | Admin menu and pages |
| `includes/class-settings.php` | Settings management |
| `includes/class-logger.php` | Logging functionality |

---

## 🏷️ Versioning

We use [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0): Breaking changes
- **MINOR** (0.X.0): New features, backward compatible
- **PATCH** (0.0.X): Bug fixes

---

## 📜 License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later.

---

## 💬 Getting Help

- **Questions?** Open a discussion on GitHub
- **Stuck?** Check existing issues and PRs
- **Need review?** Tag maintainers in your PR

---

## 🙏 Thank You!

Every contribution helps make Microsoft WP better. We appreciate your time and effort!


