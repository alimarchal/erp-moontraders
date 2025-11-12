#!/usr/bin/env python3
"""
Parse SKU CSV and extract product information to JSON format for seeding.
Extracts: brand, pack_size, units, pricing from product names.
"""

import csv
import json
import re
from pathlib import Path

def extract_product_info(name, units, invoice_price, retail_price):
    """
    Extract product information from name.
    Format examples:
    - "Nido Powder 12×900g" -> brand: Nido, product_name: Nido Powder, pack_size: 12×900g
    - "Everyday Kashmiri 3in1 60(10x18g)" -> brand: Everyday, product_name: Everyday Kashmiri 3in1
    """
    
    # Extract pack size (numbers followed by x/× and weight/count)
    pack_pattern = r'(\d+)[x×](\d+\.?\d*)(g|kg|ml|l|gm)?'
    pack_match = re.search(pack_pattern, name, re.IGNORECASE)
    
    pack_size = None
    if pack_match:
        count = pack_match.group(1)
        amount = pack_match.group(2)
        unit = pack_match.group(3) or 'g'
        pack_size = f"{count}x{amount}{unit}"
    
    # Extract brand (first word or first two words for common brands)
    name_clean = name.strip()
    words = name_clean.split()
    
    # Common brands in dataset
    two_word_brands = ['Nestle Buniyad', 'Nido 1+', 'Nido 3+', 'Nido SAN']
    brand = None
    product_name = name_clean
    
    for two_word in two_word_brands:
        if name_clean.startswith(two_word):
            brand = two_word
            product_name = name_clean
            break
    
    if not brand:
        # Single word brands
        brand = words[0] if words else 'Unknown'
    
    # Clean product name - remove extra spaces
    product_name = re.sub(r'\s+', ' ', product_name).strip()
    
    # Extract conversion factor (same as units if numeric)
    conversion_factor = 1
    try:
        conversion_factor = int(units) if units and units.strip().isdigit() else 1
    except:
        conversion_factor = 1
    
    return {
        'brand': brand,
        'product_name': product_name,
        'pack_size': pack_size,
        'uom_conversion_factor': conversion_factor
    }

def parse_csv_to_json(csv_path, json_path):
    """Parse CSV and create JSON with extracted product information."""
    
    products = []
    
    with open(csv_path, 'r', encoding='utf-8-sig') as csvfile:
        reader = csv.DictReader(csvfile)
        
        for idx, row in enumerate(reader, start=1):
            supplier_id = row.get('supplier_id', '').strip()
            sku = row.get('SKU', '').strip()
            units = row.get('Units', '').strip()
            invoice_price = row.get('Invoice Price', '0').strip()
            retail_price = row.get('Retail Price', '0').strip()
            
            if not sku:
                continue
            
            # Extract product info
            info = extract_product_info(sku, units, invoice_price, retail_price)
            
            # Generate product code from SKU name
            # Take first letters of words + numbers
            words = re.findall(r'\b[A-Za-z]+|\d+', sku)
            product_code = ''.join(w[0].upper() if w.isalpha() else w for w in words[:4])
            product_code = f"SKU-{product_code}-{idx:04d}"
            
            # Clean and convert prices
            try:
                cost_price = float(invoice_price.replace(',', ''))
            except:
                cost_price = 0.0
            
            try:
                unit_sell_price = float(retail_price.replace(',', ''))
            except:
                unit_sell_price = 0.0
            
            product = {
                'product_code': product_code,
                'product_name': info['product_name'],
                'description': f"{info['product_name']} - {info['pack_size']}" if info['pack_size'] else info['product_name'],
                'supplier_id': int(supplier_id) if supplier_id and supplier_id.isdigit() else None,
                'brand': info['brand'],
                'pack_size': info['pack_size'],
                'uom_id': 1,  # Default to PCS (Pieces) - will be overridden in seeder
                'sales_uom_id': 2,  # Default to Cases - will be overridden in seeder
                'uom_conversion_factor': info['uom_conversion_factor'],
                'cost_price': round(cost_price, 2),
                'unit_sell_price': round(unit_sell_price, 2),
                'valuation_method': 'FIFO',
                'is_active': True
            }
            
            products.append(product)
    
    # Write to JSON
    with open(json_path, 'w', encoding='utf-8') as jsonfile:
        json.dump(products, jsonfile, indent=2, ensure_ascii=False)
    
    print(f"✅ Successfully parsed {len(products)} products from CSV to JSON")
    print(f"📄 Output file: {json_path}")
    return len(products)

if __name__ == '__main__':
    # Paths
    script_dir = Path(__file__).parent
    project_root = script_dir.parent
    data_dir = project_root / 'database' / 'seeders' / 'data'
    
    csv_path = data_dir / 'sku.csv'
    json_path = data_dir / 'sku.json'
    
    if not csv_path.exists():
        print(f"❌ CSV file not found: {csv_path}")
        exit(1)
    
    print(f"📊 Parsing SKU CSV...")
    print(f"📁 Input: {csv_path}")
    
    count = parse_csv_to_json(csv_path, json_path)
    
    print(f"\n✨ Done! Parsed {count} products.")
