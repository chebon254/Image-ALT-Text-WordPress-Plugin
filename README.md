# Image Alt Text Populator

A professional WordPress plugin that automatically populates all images with your website name as alt text. Built with modern PHP 8.0+ and WordPress 6.0+ best practices.

## Features

- ✅ **Auto-populate new uploads** - Automatically adds alt text to newly uploaded images
- ✅ **Bulk update existing images** - Process all existing images in your media library
- ✅ **Multiple alt text formats** - Choose from Site Name, Site Name + Filename, or Custom Text
- ✅ **Smart overwrite options** - Choose whether to overwrite existing alt text or only update empty ones
- ✅ **Real-time statistics** - See how many images have alt text at a glance
- ✅ **Progress tracking** - Visual progress bar during bulk updates
- ✅ **Batch processing** - Efficiently handles thousands of images
- ✅ **Modern UI** - Clean, intuitive admin interface

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher

## Installation

### Manual Installation

1. Download the `image-alt-text-populator` folder
2. Upload it to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Tools → Alt Text Populator** to configure settings

### ZIP Installation

1. Compress the `image-alt-text-populator` folder into a ZIP file
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Activate the plugin

## Usage

### Quick Start

1. After activation, go to **Tools → Alt Text Populator**
2. Review your current image statistics
3. Configure your preferred settings
4. Click **Update All Images** to process existing images

### Settings

#### Auto-Populate New Uploads
Enable this to automatically add alt text to all newly uploaded images.

#### Overwrite Existing Alt Text
- **Disabled (recommended)**: Only updates images without alt text
- **Enabled**: Replaces all alt text, even if already set

#### Alt Text Formats

1. **Site Name Only**
   - Example: `"My Awesome Website"`
   - Best for: Simple, consistent alt text

2. **Site Name + File Name**
   - Example: `"My Awesome Website - Product Photo"`
   - Best for: More descriptive alt text that includes file context

3. **Custom Text**
   - Example: Your custom text
   - Best for: Specific branding requirements

### Bulk Update

The bulk update feature processes all images in batches of 50 to prevent server timeouts:

1. Click the **Update All Images** button
2. Confirm the action
3. Watch the progress bar as images are processed
4. View the results summary when complete

## Features in Detail

### Automatic Alt Text for New Uploads

When enabled, every new image uploaded to your media library will automatically get alt text based on your chosen format. No manual work required!

### Smart Batch Processing

The plugin processes images in batches of 50, making it safe to use even with thousands of images. The AJAX-based system prevents timeouts and provides real-time feedback.

### Statistics Dashboard

See at a glance:
- Total number of images in your media library
- How many images have alt text
- How many images need alt text

### Multiple Format Options

Choose the alt text format that works best for your SEO strategy:
- Simple site name for brand consistency
- Site name + filename for better context
- Custom text for specific requirements

## Technical Details

### Modern WordPress Standards

- Object-oriented PHP with type declarations
- WordPress Coding Standards compliant
- Secure AJAX with nonce verification
- Proper capability checks
- Sanitization and escaping

### Performance

- Batch processing prevents timeouts
- Efficient database queries
- Minimal server load
- Works with any number of images

### Security

- Nonce verification for all AJAX requests
- Capability checks (`manage_options`)
- Input sanitization
- Output escaping
- No SQL injection vulnerabilities

## File Structure

```
image-alt-text-populator/
├── image-alt-text-populator.php   # Main plugin file
├── includes/
│   └── admin-page.php             # Admin interface template
├── assets/
│   ├── css/
│   │   └── admin.css              # Admin styles
│   └── js/
│       └── admin.js               # Admin JavaScript
└── README.md                      # This file
```

## Frequently Asked Questions

### Will this work with existing images?

Yes! Use the "Bulk Update" feature to process all existing images in your media library.

### Can I choose which images to update?

The plugin processes all images, but you can choose whether to overwrite existing alt text or only update empty ones.

### What if I have thousands of images?

No problem! The plugin uses batch processing to handle any number of images without timing out.

### Can I customize the alt text?

Yes! Choose from three format options or use custom text.

### Will this slow down my website?

No. The plugin only runs when processing images or uploading new ones. It has no impact on front-end performance.

## Support

For issues, questions, or feature requests, please create an issue on the GitHub repository.

## Changelog

### Version 1.0.0
- Initial release
- Auto-populate new uploads
- Bulk update existing images
- Multiple alt text formats
- Statistics dashboard
- Modern admin interface

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed with ❤️ for better SEO and accessibility.

## Roadmap

Potential future features:
- [ ] Schedule automatic updates
- [ ] Category/tag-based alt text
- [ ] AI-powered image description
- [ ] Export/import settings
- [ ] Multi-language support
- [ ] Custom post type support
- [ ] Advanced formatting options

---

**Note**: This plugin modifies image alt text in your media library. Always backup your database before making bulk changes.
