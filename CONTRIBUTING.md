# Contributing to ctrlClus

We welcome contributions from the community! Whether you're fixing bugs, improving documentation, or adding new features, your help is appreciated.

## 🚀 Quick Start

1. **⭐ Star the repository** if you find it useful
2. **🍴 Fork the repository** to your GitHub account
3. **📝 Create an issue** to discuss your ideas before coding
4. **🔧 Submit pull requests** with your improvements

## 🐛 Reporting Issues

Found a bug or have a feature request?

1. **Search existing issues** to avoid duplicates
2. **Use issue templates** when creating new issues
3. **Provide detailed information**:
   - Operating system and version
   - PHP and MongoDB versions
   - Steps to reproduce
   - Expected vs actual behavior

## 💡 Feature Requests

We're always interested in new ideas! Before submitting:

1. **Check if it aligns** with the project goals
2. **Consider enterprise requirements** - this tool is used in production
3. **Provide use cases** and examples

## 🔧 Development Setup

```bash
# 1. Fork and clone
git clone https://github.com/YOUR-USERNAME/ctrlClus.git
cd ctrlClus

# 2. Install dependencies
composer install
cd lib && composer install

# 3. Set up configuration
cp config.template.php config.php
# Edit config.php with your MongoDB settings

# 4. Set up MongoDB
mongosh < scripts/mongodb_init.js
```

## 📋 Pull Request Process

1. **Create a feature branch**: `git checkout -b feature/amazing-feature`
2. **Make your changes** with clear, commented code
3. **Test thoroughly** - this is used in production environments
4. **Update documentation** if needed
5. **Submit PR** with detailed description

## 🏢 Enterprise Considerations

This project is used in production by:
- NEXI Payment Systems (2022+)
- PosteItaliane (2025+)

Please consider:
- **Backward compatibility**
- **Performance impact**
- **Security implications**
- **Enterprise deployment scenarios**

## 📞 Getting Help

- 💬 **GitHub Discussions** for questions and ideas
- 🐛 **GitHub Issues** for bugs and feature requests
- 🌐 **Professional consulting** available at [giorgio.chessari.it](http://giorgio.chessari.it)

## 🙏 Recognition

Contributors will be:
- Listed in our contributors section
- Mentioned in release notes
- Credited in documentation

**Thank you for making ctrlClus better!** 🎉