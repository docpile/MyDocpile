# MyDocpile Share

Welcome to the example `README.md`. This file demonstrates all the formatting options supported by our custom Markdown parser, including standard syntax and our unique extensions.

---

## 📝 Typography & Text Formatting

Standard formatting is fully supported out of the box:
* **Bold text** uses double asterisks or double underscores.
* *Italic text* uses single asterisks or single underscores.
* ~~Strikethrough text~~ uses double tildes.

### Custom Extensions
We also support some unique inline formatting to make your documents stand out:
* ==Highlighted Text== uses double equals signs.
* You can use [color:red]named colors[/color] or [color:#0078d4]hex codes[/color] using our custom color tags!

## 📋 Lists

### Unordered Lists
* Nginx configuration
* Bitlocker mounting scripts
* GitHub synchronization

### Ordered Lists
1. Prepare the USB drive.
2. Mount the encrypted partition.
3. Sync the repository.

### Task Lists
- [x] Write the standalone PHP Markdown parser.
- [x] Add task list and table support.
- [ ] Add nested list parsing.
- [ ] Deploy to the Ubuntu 24.04 production server.

## 💻 Code Formatting

You can format `inline code commands` using single backticks. For longer scripts or configurations, use fenced code blocks:

```php
/**
 * Example of our custom parsing logic
 */
function highlightText($text) {
    // Replaces ==text== with HTML mark tags
    return preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $text);
}
```

## 💬 Blockquotes

> **Note:** This is a standard blockquote.
> It is highly useful for callouts, warnings, or emphasizing specific instructions.
>
> They easily support multiple paragraphs.

## 🔗 Links and Images

**Links:**
You can use [Standard Markdown Links](https://github.com) or strict autolinks like <https://example.com>.

**Images:**
![Tux, the Linux mascot](https://upload.wikimedia.org/wikipedia/commons/a/af/Tux.png)

## 📊 Tables

Data can be organized into clean, responsive tables:

| Feature | Supported | Custom Syntax |
| :--- | :---: | :---: |
| Bold / Italic | Yes | No |
| Highlighting | Yes | `==text==` |
| Text Colors | Yes | `[color:HEX]text[/color]` |
| Task Lists | Yes | No |
| File Previews | Yes | No |

---

[FOOTER]

### 📌 Footer Information
*Because the `[FOOTER]` tag was used above, everything in this section will be visually detached and placed at the very bottom of the directory listing (if your configuration is set to 'top').*