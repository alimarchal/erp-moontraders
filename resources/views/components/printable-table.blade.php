{{-- resources/views/components/printable-table.blade.php --}}
@props([
    'data' => [],
    'headers' => [],
    'title' => 'Data Report',
    'showActions' => true,
    'actionButtons' => null,
    'organization' => 'Organization Name'
])

<div class="printable-table-container">
    <!-- Print Header -->
    <div class="print-header hidden print:block text-center mb-6 pb-4 border-b-2 border-black">
        <h1 class="text-xl font-bold text-black uppercase">{{ $title }}</h1>
        <p class="text-sm text-black mt-2">Generated on: <span class="print-date"></span></p>
        <p class="text-sm text-black">{{ $organization }}</p>
    </div>

    <!-- Download Button -->
    @if($showActions)
    <div class="mb-4 print:hidden">
        <button onclick="downloadPDF('{{ $title }}')" 
                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Download PDF
        </button>
    </div>
    @endif

    <!-- Table -->
    <div class="data-table bg-white border border-gray-400 print:border-black overflow-hidden">
        @if (count($data) > 0)
            <table class="w-full border-collapse table-auto">
                <thead>
                    <tr class="bg-gray-100 print:bg-gray-200">
                        @foreach($headers as $header)
                            <th class="border border-gray-400 print:border-black px-3 py-2 text-left text-xs font-bold text-black uppercase">
                                {{ $header }}
                            </th>
                        @endforeach
                        @if($showActions && $actionButtons)
                            <th class="border border-gray-400 print:border-black px-3 py-2 text-center text-xs font-bold text-black uppercase print:hidden">
                                Actions
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $index => $row)
                        <tr class="hover:bg-gray-50 print:hover:bg-transparent">
                            @foreach($row as $key => $value)
                                @if(!in_array($key, ['actions']))
                                    <td class="border border-gray-400 print:border-black px-3 py-2 text-xs text-black break-words">
                                        @if(is_array($value))
                                            {{ implode(', ', $value) }}
                                        @elseif($value instanceof \Carbon\Carbon)
                                            {{ $value->format('d/m/Y') }}
                                        @elseif(is_bool($value))
                                            {{ $value ? 'Yes' : 'No' }}
                                        @else
                                            {{ Str::limit($value, 60) }}
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                            
                            @if($showActions && $actionButtons)
                                <td class="border border-gray-400 print:border-black px-3 py-2 text-center print:hidden">
                                    <div class="flex justify-center space-x-1">
                                        {!! $actionButtons($row, $index) !!}
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12 border border-gray-400">
                <p class="text-gray-600">No data found.</p>
            </div>
        @endif
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 0.5in;
    }
    
    body * {
        visibility: hidden;
    }
    
    .printable-table-container, .printable-table-container * {
        visibility: visible;
    }
    
    .printable-table-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .print\:hidden {
        display: none !important;
    }
    
    .print\:block {
        display: block !important;
    }
    
    .print\:bg-gray-200 {
        background-color: #e5e7eb !important;
    }
    
    .print\:border-black {
        border-color: #000 !important;
    }
    
    table {
        font-size: 10px !important;
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    th, td {
        padding: 6px 4px !important;
        word-wrap: break-word;
    }
    
    thead {
        display: table-header-group;
    }
}
</style>

<!-- PDF Generation Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const printDates = document.querySelectorAll('.print-date');
    printDates.forEach(el => {
        el.textContent = new Date().toLocaleDateString();
    });
});

function downloadPDF(title = 'Data Report') {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    
    // Header
    pdf.setFontSize(14);
    pdf.setFont(undefined, 'bold');
    pdf.text(title.toUpperCase(), 105, 20, { align: 'center' });
    
    pdf.setFontSize(10);
    pdf.setFont(undefined, 'normal');
    pdf.text('Generated: ' + new Date().toLocaleDateString(), 105, 30, { align: 'center' });
    
    // Get table
    const table = document.querySelector('.data-table table');
    const rows = table.querySelectorAll('tr');
    const headers = Array.from(table.querySelectorAll('th')).filter(th => !th.classList.contains('print:hidden'));
    
    let yPos = 45;
    const pageHeight = 280;
    const margin = 15;
    const pageWidth = 180;
    
    // Calculate column widths based on content
    const numCols = headers.length;
    const colWidth = (pageWidth - (margin * 2)) / numCols;
    
    // Table headers
    pdf.setFontSize(8);
    pdf.setFont(undefined, 'bold');
    
    let xPos = margin;
    headers.forEach((header, index) => {
        pdf.rect(xPos, yPos, colWidth, 8);
        const text = header.textContent.trim();
        pdf.text(text, xPos + 2, yPos + 6);
        xPos += colWidth;
    });
    
    yPos += 8;
    pdf.setFont(undefined, 'normal');
    
    // Table data
    for (let i = 1; i < rows.length; i++) {
        const cells = Array.from(rows[i].querySelectorAll('td')).filter(td => !td.classList.contains('print:hidden'));
        
        // Check for new page
        if (yPos > pageHeight - 20) {
            pdf.addPage();
            yPos = 20;
            
            // Re-add headers
            pdf.setFont(undefined, 'bold');
            xPos = margin;
            headers.forEach((header, index) => {
                pdf.rect(xPos, yPos, colWidth, 8);
                const text = header.textContent.trim();
                pdf.text(text, xPos + 2, yPos + 6);
                xPos += colWidth;
            });
            yPos += 8;
            pdf.setFont(undefined, 'normal');
        }
        
        xPos = margin;
        cells.forEach((cell, index) => {
            let text = cell.textContent.trim();
            
            // Truncate long text
            const maxLength = Math.floor(colWidth / 2);
            if (text.length > maxLength) {
                text = text.substring(0, maxLength - 3) + '...';
            }
            
            pdf.rect(xPos, yPos, colWidth, 8);
            pdf.text(text, xPos + 2, yPos + 6);
            xPos += colWidth;
        });
        
        yPos += 8;
    }
    
    // Save PDF
    const filename = title.toLowerCase().replace(/\s+/g, '-') + '-' + new Date().toISOString().split('T')[0] + '.pdf';
    pdf.save(filename);
}
</script>