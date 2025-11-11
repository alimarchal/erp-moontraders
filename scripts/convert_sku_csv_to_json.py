#!/usr/bin/env python3
"""
Convert SKU CSV to JSON format for database seeding.
Handles duplicates by adding 'D' suffix.
"""

import csv
import json
from pathlib import Path
from collections import defaultdict

def convert_csv_to_json(csv_path, json_output_path):
    """
    Convert SKU CSV to JSON format.
    
    Args:
        csv_path: Path to input CSV file
        json_output_path: Path to output JSON file
    """
    
    # Track product codes to handle duplicates
    product_code_count = defaultdict(int)
    sku_data = []
    
    print(f"Reading CSV from: {csv_path}")
    
    # Use utf-8-sig to handle BOM (Byte Order Mark)
    with open(csv_path, 'r', encoding='utf-8-sig') as csvfile:
        reader = csv.DictReader(csvfile)
        
        for row in reader:
            # CSV columns: supplier_id, SKU, Units, Invoice Price, Retail Price
            # Get the SKU/product code
            original_product_code = row.get('SKU', '').strip()
            
            # Handle duplicates by adding 'D' suffix
            product_code_count[original_product_code] += 1
            if product_code_count[original_product_code] > 1:
                # Add 'D' for each duplicate (DD, DDD, etc.)
                product_code = f"{original_product_code}{'D' * (product_code_count[original_product_code] - 1)}"
            else:
                product_code = original_product_code
            
            product_name = original_product_code  # Use original SKU as product name
            
            # Extract brand from SKU (first word typically)
            brand = product_name.split()[0] if product_name else ""
            
            # Determine category based on brand or product name
            category_code = "BNSP"  # Default category, you may need to adjust this
            
            # Build the SKU object according to the JSON structure
            sku_item = {
                "product_code": product_code,
                "product_name": product_name,
                "description": product_name,
                "category_code": category_code,
                "supplier_id": int(row.get('supplier_id', 0)) if row.get('supplier_id', '').strip() else 0,
                "uom_symbol": "Pc",  # Default to Piece
                "pack_size": row.get('Units', '').strip(),
                "brand": brand,
                "weight": 0,
                "unit_price": float(row.get('Retail Price', 0)) if row.get('Retail Price', '').strip() else 0,
                "cost_price": float(row.get('Invoice Price', 0)) if row.get('Invoice Price', '').strip() else 0
            }
            
            sku_data.append(sku_item)
    
    # Write JSON output
    print(f"Writing {len(sku_data)} SKU items to: {json_output_path}")
    
    with open(json_output_path, 'w', encoding='utf-8') as jsonfile:
        json.dump(sku_data, jsonfile, indent=4, ensure_ascii=False)
    
    print("Conversion complete!")
    print(f"Total items: {len(sku_data)}")
    
    # Show duplicate info
    duplicates = {k: v for k, v in product_code_count.items() if v > 1}
    if duplicates:
        print(f"\nFound {len(duplicates)} product codes with duplicates:")
        for code, count in sorted(duplicates.items(), key=lambda x: x[1], reverse=True):
            print(f"  - {code}: {count} occurrences (suffixes: {', '.join([code + 'D' * i for i in range(1, count)])})")

if __name__ == "__main__":
    # Define paths
    csv_path = Path(__file__).parent.parent / "database" / "seeders" / "data" / "sku.csv"
    json_output_path = Path(__file__).parent.parent / "database" / "seeders" / "data" / "sku.json"
    
    # Allow override via command line
    import sys
    if len(sys.argv) > 1:
        csv_path = Path(sys.argv[1])
    if len(sys.argv) > 2:
        json_output_path = Path(sys.argv[2])
    
    # Check if CSV exists
    if not csv_path.exists():
        print(f"Error: CSV file not found at {csv_path}")
        print("\nUsage:")
        print(f"  python {Path(__file__).name} [csv_path] [json_output_path]")
        print("\nOr copy your SKU.csv to:")
        print(f"  {csv_path}")
        sys.exit(1)
    
    # Perform conversion
    convert_csv_to_json(csv_path, json_output_path)
