/**
 * Reusable PDF Generator Component
 * 
 * This module provides a flexible client-side PDF generation system using jsPDF.
 * It can be configured for different data types and document layouts.
 * 
 * @author MoonTrader ERP
 * @version 1.0.0
 */

class PDFGenerator {
    constructor(config = {}) {
        this.config = {
            buttonId: config.buttonId || 'pdf-download-btn',
            documentTitle: config.documentTitle || 'Document',
            fileName: config.fileName || 'document.pdf',
            orientation: config.orientation || 'p', // 'p' = portrait, 'l' = landscape
            format: config.format || 'a4',
            logoUrl: config.logoUrl || null,
            logoSize: config.logoSize || { width: 140, height: 50 },
            appName: config.appName || '',
            debug: config.debug || false,
            ...config
        };
        
        this.jsPDFLoaded = false;
        this.qrCodeLoaded = false;
        this.init();
    }
    
    /**
     * Log messages (only if debug is enabled)
     */
    log(...args) {
        if (this.config.debug) {
            try {
                console.log('[PDF Generator]', ...args);
            } catch (e) {}
        }
    }
    
    /**
     * Update button status
     */
    status(msg) {
        const btn = document.getElementById(this.config.buttonId);
        if (btn) {
            btn.dataset.stage = msg;
            btn.title = 'PDF: ' + msg;
        }
        this.log('Status:', msg);
    }
    
    /**
     * Load external script once
     */
    loadScriptOnce(id, src, readyTest) {
        return new Promise((resolve, reject) => {
            const existing = document.getElementById(id);
            const isReady = function() {
                try {
                    return !readyTest || readyTest();
                } catch (e) {
                    return false;
                }
            };
            
            if (existing) {
                this.log('script already present', id);
                if (isReady()) return resolve(true);
                existing.addEventListener('load', () => {
                    if (isReady()) resolve(true);
                    else reject(new Error('Library not ready after load ' + id));
                });
                existing.addEventListener('error', () => reject(new Error('Load failed ' + src)));
                return;
            }
            
            const s = document.createElement('script');
            s.id = id;
            s.src = src;
            s.async = true;
            s.onload = () => {
                this.log('loaded', id);
                if (isReady()) resolve(true);
                else reject(new Error('Library not ready ' + id));
            };
            s.onerror = () => {
                this.log('failed load', id, src);
                reject(new Error('Load failed ' + src));
            };
            document.head.appendChild(s);
        });
    }
    
    /**
     * Ensure all required libraries are loaded
     */
    async ensureLibraries() {
        // Load jsPDF
        if (!(window.jspdf && window.jspdf.jsPDF)) {
            try {
                await this.loadScriptOnce(
                    'jspdf-core',
                    'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
                    () => window.jspdf && window.jspdf.jsPDF
                );
            } catch (e) {
                this.log('primary CDN failed, trying fallback');
                await this.loadScriptOnce(
                    'jspdf-core-fb',
                    'https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js',
                    () => window.jspdf && window.jspdf.jsPDF
                );
            }
        }
        
        if (!(window.jspdf && window.jspdf.jsPDF)) {
            throw new Error('jsPDF load failed');
        }
        
        this.jsPDFLoaded = true;
        
        // Load QRCode library if needed
        if (this.config.includeQRCode && !window.QRCode) {
            try {
                await this.loadScriptOnce(
                    'qrcode-lib',
                    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
                    () => window.QRCode
                );
                this.qrCodeLoaded = true;
            } catch (e) {
                this.log('QRCode library failed to load', e);
            }
        }
    }
    
    /**
     * Format date
     */
    formatDate(dt, format = 'short') {
        if (!dt) return '-';
        try {
            const date = new Date(dt);
            if (format === 'short') {
                return date.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
            } else if (format === 'long') {
                return date.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            return date.toLocaleDateString();
        } catch (e) {
            return dt;
        }
    }
    
    /**
     * Load image as data URL
     */
    async loadImageAsDataURL(url) {
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Image fetch failed: ' + res.status);
            const blob = await res.blob();
            return await new Promise((resolve) => {
                const r = new FileReader();
                r.onload = () => resolve(r.result);
                r.readAsDataURL(blob);
            });
        } catch (e) {
            this.log('Failed to load image:', url, e);
            return null;
        }
    }
    
    /**
     * Add logo to PDF
     */
    async addLogo(doc, x, y) {
        if (!this.config.logoUrl) return;
        
        try {
            const logoData = await this.loadImageAsDataURL(this.config.logoUrl);
            if (logoData) {
                const ext = this.config.logoUrl.toLowerCase().includes('.png') ? 'PNG' : 'JPEG';
                doc.addImage(
                    logoData,
                    ext,
                    x,
                    y,
                    this.config.logoSize.width,
                    this.config.logoSize.height
                );
            }
        } catch (e) {
            this.log('Logo load skipped', e.message);
        }
    }
    
    /**
     * Draw header section
     */
    drawHeader(doc, title, subtitle = null) {
        const pageWidth = doc.internal.pageSize.getWidth();
        let y = 80;
        
        // Title
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(18);
        doc.setTextColor(20);
        doc.text(title, pageWidth / 2, y, { align: 'center' });
        
        // Subtitle
        if (subtitle) {
            y += 20;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.setTextColor(60);
            doc.text(subtitle, pageWidth / 2, y, { align: 'center' });
        }
        
        return y + 20;
    }
    
    /**
     * Draw table
     */
    drawTable(doc, headers, rows, startY, options = {}) {
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = options.margin || 40;
        const tableWidth = pageWidth - (margin * 2);
        const rowHeight = options.rowHeight || 25;
        const fontSize = options.fontSize || 9;
        
        let y = startY;
        
        // Calculate column widths
        const colCount = headers.length;
        const colWidths = options.columnWidths || Array(colCount).fill(tableWidth / colCount);
        
        // Draw header
        doc.setFillColor(240, 240, 240);
        doc.rect(margin, y, tableWidth, rowHeight, 'F');
        
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(fontSize);
        doc.setTextColor(30);
        
        let xPos = margin;
        headers.forEach((header, i) => {
            const align = header.align || 'left';
            let textX = xPos + 10;
            if (align === 'center') textX = xPos + (colWidths[i] / 2);
            if (align === 'right') textX = xPos + colWidths[i] - 10;
            
            doc.text(header.label || header, textX, y + 16, { align: align });
            xPos += colWidths[i];
        });
        
        y += rowHeight;
        doc.setDrawColor(200);
        doc.line(margin, y, pageWidth - margin, y);
        
        // Draw rows
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(60);
        
        rows.forEach((row, rowIndex) => {
            // Check if we need a new page
            if (y > pageHeight - 100) {
                doc.addPage();
                y = 60;
                
                // Redraw header on new page
                doc.setFillColor(240, 240, 240);
                doc.rect(margin, y, tableWidth, rowHeight, 'F');
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(30);
                
                xPos = margin;
                headers.forEach((header, i) => {
                    const align = header.align || 'left';
                    let textX = xPos + 10;
                    if (align === 'center') textX = xPos + (colWidths[i] / 2);
                    if (align === 'right') textX = xPos + colWidths[i] - 10;
                    
                    doc.text(header.label || header, textX, y + 16, { align: align });
                    xPos += colWidths[i];
                });
                
                y += rowHeight;
                doc.line(margin, y, pageWidth - margin, y);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(60);
            }
            
            y += rowHeight;
            xPos = margin;
            
            row.forEach((cell, i) => {
                const align = headers[i].align || 'left';
                let textX = xPos + 10;
                if (align === 'center') textX = xPos + (colWidths[i] / 2);
                if (align === 'right') textX = xPos + colWidths[i] - 10;
                
                const text = String(cell || '-');
                doc.text(text, textX, y - 8, { align: align });
                xPos += colWidths[i];
            });
            
            doc.line(margin, y, pageWidth - margin, y);
        });
        
        return y + 10;
    }
    
    /**
     * Draw footer
     */
    drawFooter(doc, text = null, pageNumber = true) {
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(120);
        
        if (text) {
            doc.text(text, 40, pageHeight - 40);
        }
        
        doc.text('Generated on: ' + this.formatDate(new Date(), 'long'), pageWidth - 40, pageHeight - 40, { align: 'right' });
        
        if (pageNumber) {
            doc.setFontSize(8);
            doc.setTextColor(90);
            doc.text('Page 1 of 1', pageWidth / 2, pageHeight - 20, { align: 'center' });
        }
    }
    
    /**
     * Generate PDF - to be overridden by specific implementation
     */
    async generate() {
        throw new Error('generate() method must be implemented by subclass or passed as config.generateFn');
    }
    
    /**
     * Main PDF generation handler
     */
    async runPdf() {
        const btn = document.getElementById(this.config.buttonId);
        if (!btn) {
            this.log('runPdf: no button found');
            return;
        }
        
        this.log('runPdf invoked');
        this.status('clicked');
        
        const originalHTML = btn.innerHTML;
        
        try {
            btn.disabled = true;
            btn.classList.add('opacity-50');
            btn.innerHTML = '<svg class="animate-spin h-4 w-4 mr-2 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Building PDF...';
            
            this.status('loading libs');
            await this.ensureLibraries();
            this.log('libs ready');
            this.status('libs ready');
            
            this.status('building pdf');
            
            // Call the generate function
            if (this.config.generateFn && typeof this.config.generateFn === 'function') {
                await this.config.generateFn(this);
            } else {
                await this.generate();
            }
            
            this.status('done');
            
        } catch (e) {
            this.log('error', e);
            this.status('error: ' + e.message);
            console.error(e);
            alert('Failed to build PDF: ' + e.message + ' (see console for details)');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('opacity-50');
                btn.innerHTML = originalHTML;
            }
        }
    }
    
    /**
     * Initialize the PDF generator
     */
    init() {
        const self = this;
        
        function bindListener() {
            const btn = document.getElementById(self.config.buttonId);
            if (!btn || btn.dataset.pdfInit) {
                self.log('init: button missing or already init');
            } else {
                btn.dataset.pdfInit = '1';
                btn.addEventListener('click', () => self.runPdf(), { once: false });
                self.log('init: listener bound');
                self.status('ready');
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindListener);
        } else {
            bindListener();
        }
    }
}

// Export for use in modules or global scope
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PDFGenerator;
} else {
    window.PDFGenerator = PDFGenerator;
}
