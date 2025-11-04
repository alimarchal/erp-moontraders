# PDF Component Installation & Setup

## Prerequisites

This PDF generation system requires the Laravel DomPDF package for server-side PDF generation. The client-side generation works without any additional packages.

## Installation

### Step 1: Install DomPDF Package (Required for Server-Side PDFs)

Run the following command in your terminal:

```bash
composer require barryvdh/laravel-dompdf
```

### Step 2: Publish Configuration (Optional)

If you want to customize DomPDF settings:

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

This will create a `config/dompdf.php` file where you can adjust settings like:
- Default paper size
- Orientation
- Font directory
- DPI settings

## Verification

### Test Server-Side PDF

Visit the vehicles page in your application:
```
/vehicles
```

Click the "Export PDF (Server)" button. If DomPDF is installed correctly, a PDF file should download.

### Test Client-Side PDF

On the same vehicles page, click "Export PDF (Client)". This should work immediately without any additional setup, as it uses browser-based JavaScript libraries.

## Configuration

### DomPDF Settings

Edit `config/dompdf.php` (after publishing) to customize:

```php
return [
    'show_warnings' => false,
    'orientation' => 'portrait',
    'defines' => [
        'font_dir' => storage_path('fonts/'),
        'font_cache' => storage_path('fonts/'),
        'temp_dir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()),
        'enable_font_subsetting' => false,
        'pdf_backend' => 'CPDF',
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_font' => 'serif',
        'dpi' => 96,
        'enable_php' => false,
        'enable_javascript' => true,
        'enable_remote' => true,
        'font_height_ratio' => 1.1,
        'enable_html5_parser' => false,
    ],
];
```

### Logo Path Configuration

Make sure your logo file exists at:
```
public/icons-images/logo-imusafir.jpeg
```

Or update the path in:
- `resources/views/vehicles/pdf.blade.php` (line 93)
- `resources/views/vehicles/index.blade.php` (in the @push('scripts') section)

## File Structure

The PDF generation system consists of:

```
app/
├── Http/
│   └── Controllers/
│       └── VehicleController.php        # Server-side PDF method
public/
├── jsandcss/
│   └── pdf-generator.js                 # Reusable JS class
├── icons-images/
│   └── logo-imusafir.jpeg              # Logo file
resources/
├── views/
│   ├── components/
│   │   └── pdf-download-button.blade.php  # Reusable button component
│   └── vehicles/
│       ├── index.blade.php              # Client-side implementation
│       └── pdf.blade.php                # Server-side PDF template
routes/
└── web.php                              # PDF export route
docs/
├── PDF_GENERATION_COMPONENT.md          # Full documentation
├── PDF_QUICK_REFERENCE.md               # Quick reference
└── PDF_INSTALLATION.md                  # This file
```

## Troubleshooting

### Issue: "Class 'Barryvdh\DomPDF\Facade\Pdf' not found"

**Solution:** Install the DomPDF package:
```bash
composer require barryvdh/laravel-dompdf
```

### Issue: Logo not showing in PDF

**Solution:** 
1. Verify the logo file exists at the specified path
2. Use absolute paths with `public_path()` in server-side PDFs:
   ```php
   <img src="{{ public_path('icons-images/logo.jpeg') }}" alt="Logo">
   ```
3. Use `asset()` helper for client-side PDFs:
   ```javascript
   logoUrl: '{{ asset("icons-images/logo.jpeg") }}'
   ```

### Issue: Client-side PDF not generating

**Solution:**
1. Check browser console for JavaScript errors
2. Ensure the PDF generator script is loaded:
   ```blade
   <script src="{{ asset('jsandcss/pdf-generator.js') }}"></script>
   ```
3. Verify the button ID matches the configuration:
   ```javascript
   buttonId: 'vehicle-pdf-btn'  // Must match button id attribute
   ```

### Issue: Fonts not rendering correctly in PDF

**Solution:** Use web-safe fonts in your PDF views:
```css
font-family: 'DejaVu Sans', 'Arial', sans-serif;
```

For custom fonts, publish the DomPDF config and add fonts to the font directory.

### Issue: Memory errors with large datasets

**Solution:** Increase PHP memory limit or use pagination:

**Option 1: Increase memory limit**
```php
ini_set('memory_limit', '512M');
```

**Option 2: Use pagination**
```php
public function exportPdf(Request $request)
{
    $vehicles = Vehicle::query()
        ->with(['employee', 'company', 'supplier'])
        ->limit(500) // Limit results
        ->get();
    
    // ... rest of code
}
```

**Option 3: Use client-side for large datasets**
Client-side generation can handle large datasets better for viewing, but still has limits around 1000+ rows.

### Issue: Styles not applying in server-side PDF

**Solution:**
1. Use inline styles instead of external CSS files
2. Avoid complex CSS (flexbox, grid, transforms)
3. Use tables for layout instead of divs
4. Use absolute units (px, pt) instead of relative (em, rem)

## Performance Optimization

### Server-Side
```php
// Eager load relationships to avoid N+1 queries
$vehicles = Vehicle::with(['employee', 'company', 'supplier'])->get();

// Use chunk for large datasets
Vehicle::chunk(1000, function($vehicles) {
    // Process in batches
});
```

### Client-Side
```javascript
// Show progress for large datasets
if (items.length > 100) {
    btn.innerHTML = 'Processing ' + processed + ' of ' + items.length + '...';
}

// Consider pagination for very large datasets
if (items.length > 500) {
    alert('Dataset too large. Please use server-side export.');
    return;
}
```

## Security Considerations

### Server-Side
```php
// Always authorize PDF exports
public function exportPdf(Request $request)
{
    // Check permissions
    if (!auth()->user()->can('export-vehicles')) {
        abort(403, 'Unauthorized');
    }
    
    // ... rest of code
}
```

### Client-Side
```javascript
// Sanitize data before including in PDF
const sanitizedData = items.map(item => ({
    ...item,
    name: String(item.name || '').replace(/<[^>]*>/g, '') // Remove HTML
}));
```

## Additional Resources

- **Full Documentation:** `docs/PDF_GENERATION_COMPONENT.md`
- **Quick Reference:** `docs/PDF_QUICK_REFERENCE.md`
- **Example Implementation:** `resources/views/vehicles/index.blade.php`
- **DomPDF Documentation:** https://github.com/barryvdh/laravel-dompdf
- **jsPDF Documentation:** https://github.com/parallax/jsPDF

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the full documentation in `docs/PDF_GENERATION_COMPONENT.md`
3. Examine the working example in the vehicles module
4. Check browser console for JavaScript errors (client-side)
5. Check Laravel logs for PHP errors (server-side): `storage/logs/laravel.log`
