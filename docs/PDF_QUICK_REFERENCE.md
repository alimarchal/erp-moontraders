# PDF Component Quick Reference

## Quick Start

### Server-Side PDF (3 steps)

1. **Controller:**
```php
use Barryvdh\DomPDF\Facade\Pdf;

public function exportPdf() {
    $data = YourModel::all();
    $pdf = Pdf::loadView('your-view.pdf', compact('data'));
    return $pdf->download('export.pdf');
}
```

2. **Route:**
```php
Route::get('export/pdf', [YourController::class, 'exportPdf'])->name('export.pdf');
```

3. **View:**
```blade
<x-pdf-download-button 
    type="server" 
    :route="route('export.pdf')" 
    label="Export PDF" />
```

---

### Client-Side PDF (2 steps)

1. **Button:**
```blade
<x-pdf-download-button 
    id="pdf-btn" 
    type="client" 
    label="Download" />
```

2. **Script:**
```blade
@push('scripts')
<script src="{{ asset('jsandcss/pdf-generator.js') }}"></script>
<script>
const data = @json($yourData);

new PDFGenerator({
    buttonId: 'pdf-btn',
    fileName: 'export.pdf',
    generateFn: async function(gen) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        
        // Your PDF logic
        doc.text('Hello World', 40, 60);
        
        doc.save(gen.config.fileName);
    }
});
</script>
@endpush
```

---

## Button Component Props

```blade
<x-pdf-download-button 
    id="btn-id"              {{-- Button ID --}}
    type="client|server"     {{-- Generation type --}}
    :route="route('...')"    {{-- Server route (if server) --}}
    label="Text"             {{-- Button text --}}
    variant="indigo|blue|green|gray"  {{-- Color --}}
    size="sm|md|lg"          {{-- Size --}}
    :icon="true|false"       {{-- Show icon --}}
/>
```

---

## PDFGenerator Config

```javascript
new PDFGenerator({
    buttonId: 'btn-id',           // Required
    fileName: 'file.pdf',         // Default: 'document.pdf'
    documentTitle: 'My Doc',      // Default: 'Document'
    orientation: 'p',             // 'p' or 'l'
    format: 'a4',                 // Page format
    logoUrl: '/logo.png',         // Logo path
    logoSize: {width: 140, height: 50},
    appName: 'App Name',
    debug: true,                  // Enable logs
    includeQRCode: false,         // Load QR library
    generateFn: async (gen) => {} // Required: PDF logic
})
```

---

## Helper Methods

```javascript
// Format date
gen.formatDate(date, 'short') // "04 Nov 2025"
gen.formatDate(date, 'long')  // "04 November 2025, 02:30"

// Add logo
await gen.addLogo(doc, x, y)

// Draw header (returns new Y position)
y = gen.drawHeader(doc, 'TITLE', 'Subtitle')

// Draw table
y = gen.drawTable(doc, headers, rows, startY, {
    margin: 40,
    rowHeight: 25,
    fontSize: 9,
    columnWidths: [100, 200, 150]
})

// Draw footer
gen.drawFooter(doc, 'Footer text', showPageNumber)
```

---

## Table Headers Format

```javascript
const headers = [
    { label: 'Name', align: 'left' },
    { label: 'Amount', align: 'right' },
    { label: 'Status', align: 'center' }
]

// Rows are simple arrays
const rows = [
    ['John Doe', '$100', 'Active'],
    ['Jane Smith', '$200', 'Pending']
]
```

---

## Common Patterns

### Basic Invoice

```javascript
generateFn: async function(gen) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');
    let y = 20;
    
    await gen.addLogo(doc, 35, y);
    y = gen.drawHeader(doc, 'INVOICE', 'INV-001');
    
    // Customer info
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text('Bill To: Customer Name', 40, y);
    y += 40;
    
    // Items table
    const headers = [
        { label: 'Item', align: 'left' },
        { label: 'Qty', align: 'center' },
        { label: 'Price', align: 'right' },
        { label: 'Total', align: 'right' }
    ];
    const rows = data.items.map(i => [
        i.name, i.qty, '$' + i.price, '$' + i.total
    ]);
    y = gen.drawTable(doc, headers, rows, y);
    
    // Total
    y += 20;
    doc.setFont('helvetica', 'bold');
    doc.text('TOTAL: $' + data.total, 400, y);
    
    gen.drawFooter(doc, 'Thank you!');
    doc.save(gen.config.fileName);
}
```

### List Export

```javascript
generateFn: async function(gen) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'pt', 'a4'); // Landscape
    let y = 20;
    
    await gen.addLogo(doc, 35, y);
    y = gen.drawHeader(doc, 'ITEMS LIST');
    
    doc.setFontSize(9);
    doc.text('Total: ' + items.length, 40, y);
    y += 30;
    
    const headers = [
        { label: '#', align: 'center' },
        { label: 'Name', align: 'left' },
        { label: 'Code', align: 'left' },
        { label: 'Status', align: 'center' }
    ];
    
    const rows = items.map((item, i) => [
        i + 1, item.name, item.code, item.status
    ]);
    
    gen.drawTable(doc, headers, rows, y, {
        columnWidths: [40, 300, 200, 100]
    });
    
    gen.drawFooter(doc);
    doc.save(gen.config.fileName);
}
```

---

## Styling for Server PDFs

```css
/* Good */
font-family: 'DejaVu Sans', sans-serif;
font-size: 11px;
color: #333333;
border: 1px solid #ddd;

/* Avoid */
font-size: 1rem;        /* Use px instead */
display: flex;          /* Use tables */
position: relative;     /* Avoid positioning */
```

---

## When to Use What?

| Feature | Server-Side | Client-Side |
|---------|-------------|-------------|
| Large datasets (>500 rows) | ✅ | ❌ |
| Complex layouts | ✅ | ⚠️ |
| Quick/instant export | ❌ | ✅ |
| No server load | ❌ | ✅ |
| Email attachments | ✅ | ❌ |
| Scheduled exports | ✅ | ❌ |
| Interactive features | ❌ | ✅ |

---

## Full Example: Vehicles Index

See: `resources/views/vehicles/index.blade.php`

Both server and client-side implementations demonstrated with:
- Filter preservation
- Table formatting
- Logo inclusion
- Proper styling
- Error handling

---

## Need Help?

Full documentation: `docs/PDF_GENERATION_COMPONENT.md`

Working example: Vehicle module (`vehicles/index.blade.php`)
