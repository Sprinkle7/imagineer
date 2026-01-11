=== Imagineer - Image Converter ===
Contributors: allauddinyousafxai
Tags: image converter, webp, image optimization, bulk converter, image resize
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert and optimize images between PNG, JPG, WEBP, TIFF, BMP, and GIF formats. All features completely free!

== Description ==

**Imagineer** is a powerful, feature-complete image format converter for WordPress. Convert your images between multiple formats with an intuitive drag-and-drop interface - and all features are completely free!

= ✨ Key Features (All Free!) =

* **Multiple Format Support** - PNG, JPG, WEBP, TIFF, BMP, and GIF
* **Bulk Processing** - Convert unlimited images at once
* **Image Resizing** - Resize while converting with custom dimensions
* **Quality Control** - Fine-tune quality from 1-100
* **Drag & Drop Interface** - Modern, intuitive upload experience
* **Media Library Integration** - Convert images directly from WordPress Media Library
* **Bulk Media Library Conversion** - Select and convert multiple images at once
* **Statistics Dashboard** - Track conversions, space saved, and files processed
* **Conversion History** - View detailed conversion logs with file sizes
* **Backup & Restore** - Automatic backups before replacing originals
* **Auto-Optimize on Upload** - Automatically convert images when uploaded
* **Frontend Shortcodes** - Add conversion tools to any page for visitors
* **Professional Dialogs** - Modern dialog boxes instead of browser alerts
* **Auto-Download** - Converted images download automatically
* **Beautiful UI** - Professional, modern design with gradient cards
* **Performance Optimized** - Fast conversions with intelligent caching
* **50MB File Limit** - Handle large images with ease
* **Format Requirements Checker** - See what formats are supported on your server

= 🎯 Conversion Capabilities =

* PNG ↔ JPG
* PNG ↔ WEBP
* JPG ↔ WEBP
* TIFF ↔ PNG/JPG/WEBP
* BMP ↔ PNG/JPG/WEBP
* GIF ↔ PNG/JPG/WEBP
* And any combination between supported formats!

= 🚀 Advanced Features =

* **Bulk Conversion** - Process hundreds of images simultaneously
* **Image Resizing** - Set width and height while converting
* **Quality Optimization** - Balance file size vs quality
* **Format Statistics** - See which formats you use most
* **Space Savings Tracker** - Monitor storage space saved
* **Recent Conversions** - Quick access to conversion history
* **WooCommerce Ready** - Perfect for product images
* **Developer Friendly** - Clean code, hooks, and filters

= 📝 Frontend Shortcodes =

Add conversion tools to any page for your visitors:

* `[imagineer_png_to_jpg]` - PNG to JPG converter
* `[imagineer_jpg_to_png]` - JPG to PNG converter
* `[imagineer_to_webp]` - Convert any image to WEBP
* `[imagineer_bulk_converter]` - Bulk converter with multiple files
* `[imagineer_resize width="800"]` - Resize & convert tool
* `[imagineer_converter from="png" to="jpg"]` - Custom converter

Perfect for creating utility pages for your visitors!

= ⚡ Performance Optimized =

* Direct GD/Imagick usage for 2-3x faster conversions
* Intelligent caching system
* Memory optimization
* Format-specific optimizations
* WebP Convert library included for maximum compatibility

= 💝 Support Development =

This plugin is completely free with all features unlocked! If you find it helpful, consider [buying me a coffee](https://www.buymeacoffee.com/adusafxai) to support continued development.

= 🔧 Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* GD Library or Imagick extension (usually pre-installed)

= 📋 Format-Specific Requirements =

**PNG, JPG, WEBP, GIF, BMP:**
* Works with standard GD Library (included in PHP 7.2+)
* No additional setup required
* BMP support requires PHP 7.2+ (imagecreatefrombmp/imagebmp functions)

**TIFF Format:**
* Requires Imagick PHP extension
* Imagick must be installed on your server
* Most shared hosting providers have Imagick available
* Contact your host if TIFF conversion shows errors

**How to Check Your Server:**
* Visit the Imagineer plugin page in WordPress admin
* Check the "Performance Status" section (if visible)
* Or check with your hosting provider

**Installing Imagick (for TIFF support):**
* Most hosts: Contact support to enable Imagick extension
* VPS/Dedicated: Install via package manager (apt-get, yum, etc.)
* Example: `sudo apt-get install php-imagick` (Ubuntu/Debian)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/imagineer/` directory, or install through WordPress plugins screen
2. Activate the plugin through 'Plugins' screen in WordPress
3. A welcome screen will guide you through initial setup
4. Go to Imagineer menu in WordPress admin
5. Start converting images!

For manual installation:
1. Download the plugin ZIP file
2. Go to WordPress admin → Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click Install Now
4. Click Activate Plugin
5. Visit the Imagineer page from the WordPress menu

== Frequently Asked Questions ==

= Is this really completely free? =

Yes! All features are completely free with no limitations. No Pro version, no upsells, no hidden fees.

= Does this work on my hosting? =

Yes! The plugin works on 99% of WordPress hosts. It requires GD Library (pre-installed on most hosts) or Imagick extension.

**Format Support:**
* PNG, JPG, WEBP, GIF, BMP: Work with standard GD Library (PHP 7.2+)
* TIFF: Requires Imagick extension (contact your host if not available)

= Do I need special server setup? =

No! The plugin works out-of-the-box on standard WordPress hosting. Check the Performance Status in the plugin to see what's available on your server.

= What if WebP is not supported? =

The plugin includes WebP Convert library, so you can still create WEBP files even if your server doesn't have native WebP support.

= What if TIFF conversion doesn't work? =

TIFF format requires the Imagick PHP extension. If you see errors when converting to/from TIFF:
1. Check if Imagick is installed (ask your hosting provider)
2. Most shared hosts can enable Imagick upon request
3. For VPS/dedicated servers, install via: `sudo apt-get install php-imagick`
4. PNG, JPG, WEBP, GIF, and BMP formats work without Imagick

= Can I use this on the frontend? =

Yes! Use shortcodes to add conversion tools to any page. Perfect for creating utility pages for your visitors.

= How many files can I convert at once? =

Unlimited! The bulk converter can handle as many files as you want to upload.

= What's the file size limit? =

50MB per file. Perfect for high-resolution images and professional photography.

= Can I resize images while converting? =

Yes! Set custom width and height, and the plugin will resize while converting. Maintains aspect ratio automatically.

= Does it work with WooCommerce? =

Yes! Perfect for optimizing product images and bulk converting your product catalog.

= How do I get support? =

Visit the WordPress.org support forum for this plugin. For priority support, consider [buying me a coffee](https://www.buymeacoffee.com/adusafxai)!

== Screenshots ==

1. Main converter interface with drag and drop
2. Conversion results with space saved indicator
3. Bulk conversion with multiple files
4. Media Library integration for easy conversion
5. Statistics dashboard showing conversions and space saved
6. Settings page with shortcodes documentation

== Changelog ==

= 1.0.0 =
* Initial release
* Multi-format conversion (PNG, JPG, WEBP, TIFF, BMP, GIF)
* Bulk processing - convert unlimited files
* Image resizing with custom dimensions
* Quality control (1-100)
* Drag and drop interface
* Media Library integration with bulk conversion
* Statistics dashboard with conversion history
* Space saved tracking
* Auto-download functionality
* Frontend shortcodes for public conversion tools
* Professional dialog system (replaces browser alerts)
* Backup and restore functionality
* Auto-optimize on upload
* Performance optimizations
* WebP Convert library integration
* Beautiful modern UI with gradient cards
* Format requirements checker
* All features completely free!

== Upgrade Notice ==

= 1.0.0 =
Initial release of Imagineer image converter plugin with all features completely free!

== Third-Party Services ==

This plugin uses the WebP Convert library (rosell-dk/webp-convert) for enhanced WebP support. The library is open source and GPL-compatible. No external services or API calls are made.

== Privacy ==

This plugin does not collect any user data. All image conversions are processed locally on your server. No images are sent to external services.

== Credits ==

* Developed with ❤️ for the WordPress community
* Uses WordPress core image functions for reliability
* WebP Convert library by Bjørn Rosell
* Icons from Dashicons (WordPress core)

== Support Development ==

Love Imagineer? Support continued development by [buying me a coffee](https://www.buymeacoffee.com/adusafxai)! ☕

