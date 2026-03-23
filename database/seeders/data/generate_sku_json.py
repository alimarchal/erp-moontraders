import csv, json, re, os
from collections import Counter

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# ===== Read sku.csv =====
csv_file = os.path.join(BASE_DIR, 'sku.csv')
with open(csv_file, 'r', encoding='utf-8') as f:
    reader = csv.reader(f)
    header = next(reader)
    rows = []
    for row in reader:
        if len(row) < 5:
            continue
        sku = row[1].strip()
        supplier_id = row[0].strip()
        units = row[2].strip()
        invoice_price = row[3].strip()
        retail_price = row[4].strip()
        rows.append({
            'supplier_id': int(supplier_id),
            'sku': sku,
            'units': int(units) if units else 1,
            'invoice_price': float(invoice_price) if invoice_price else 0,
            'retail_price': float(retail_price) if retail_price else 0,
        })

print(f"Total valid SKUs from CSV: {len(rows)}")


def detect_brand(sku, supplier_id):
    s = sku.upper()
    if supplier_id == 1:
        if 'DBP' in s: return 'Dalda'
        if 'DCO' in s: return 'Dalda'
        if 'TCO' in s: return 'Dalda'
        if 'MPB' in s: return 'Dalda'
        if 'TULLO' in s or 'TBP' in s: return 'Tullo'
        if 'KALONJI' in s: return 'Dalda'
        if 'CORN' in s or 'COR OIL' in s: return 'Dalda'
        if 'POMACE' in s: return 'Dalda'
        if 'EXTRA VIRGIN' in s: return 'Dalda'
        if 'CUP SHUP' in s: return 'Cup Shup'
        return 'Dalda'
    if supplier_id == 2:
        if 'OLIVOLA' in s: return 'Olivola'
        if 'ROYAL' in s: return 'Royal'
        return 'Royal'
    if supplier_id == 3:
        if 'NIDO' in s: return 'Nido'
        if 'BUNIYAD' in s: return 'Nestle Buniyad'
        if 'EVERYDAY' in s: return 'Everyday'
        if 'LACTOGEN' in s or 'LACTOGROW' in s: return 'Lactogen'
        if 'CERELAC' in s: return 'Cerelac'
        if 'KOKO' in s: return 'Koko Krunch'
        if 'MILO' in s: return 'Milo'
        if 'PRENAN' in s: return 'NAN'
        if 'NAN ' in s or s.startswith('NAN '): return 'NAN'
        if 'NESCAFE' in s: return 'Nescafe'
        if 'TRIX' in s: return 'Nestle'
        if 'MILKPAK' in s: return 'Milkpak'
        if 'CREAM' in s: return 'Milkpak'
        if 'NESVITA' in s: return 'Nesvita'
        if 'FRUITA' in s or 'NESFRUITA' in s or 'NFV' in s: return 'Fruita Vitals'
        if 'PURE LIFE' in s or 'SPARKE' in s: return 'Pure Life'
        return 'Nestle'
    if supplier_id == 4:
        if 'OLPER' in s or 'OLP ' in s or s.startswith('OLP'): return "Olper's"
        if 'TARANG' in s: return 'Tarang'
        if 'PROCAL' in s: return 'Procal'
        if 'FCMP' in s: return 'FCMP'
        if 'OMUNG' in s: return 'Dairy Omung'
        if 'TARKA' in s: return 'Tarka'
        if 'FALVOUR' in s or 'FLAVOUR' in s: return "Olper's"
        return 'Engro'
    if supplier_id == 5:
        if 'SURF' in s: return 'Surf Excel'
        if 'RIN' in s: return 'Rin'
        if 'SUNLIGHT' in s: return 'Sunlight'
        if 'COMFORT' in s: return 'Comfort'
        if 'DOMEX' in s: return 'Domex'
        if 'VIM' in s: return 'Vim'
        if 'LIFEBUOY' in s or 'LIFEBOUY' in s: return 'Lifebuoy'
        if 'LUX' in s: return 'Lux'
        if 'FAIR' in s and 'LOVELY' in s: return 'Fair & Lovely'
        if 'PONDS' in s or 'POND ' in s: return 'Ponds'
        if 'DOVE' in s: return 'Dove'
        if 'CLEAR' in s: return 'Clear'
        if 'SUNSILK' in s: return 'Sunsilk'
        if 'TRESEME' in s: return 'TRESemme'
        if 'KNORR' in s: return 'Knorr'
        if 'RAFHAN' in s: return 'Rafhan'
        if 'GLAXOSE' in s: return 'Glaxose-D'
        if 'ENERGILE' in s: return 'Energile'
        if 'HELLMANN' in s: return "Hellmann's"
        return 'Unilever'
    if supplier_id == 6:
        if 'LIPTON' in s: return 'Lipton'
        if 'BB SUPREME' in s: return 'BB Supreme'
        return 'Lipton'
    if supplier_id == 7:
        return 'Kausar'
    if supplier_id == 8:
        return 'Peek Freans'
    if supplier_id == 9:
        return 'National'
    if supplier_id == 10:
        if 'DUNHILL' in s: return 'Dunhill'
        if 'B&H' in s: return 'Benson & Hedges'
        if 'GOLD LEAF' in s: return 'Gold Leaf'
        if 'CAPSTAN' in s: return 'Capstan'
        if 'FLAKE' in s or 'ROTHMAN' in s: return 'Rothmans'
        if 'JOHN PLAYER' in s: return 'John Player'
        if 'VELO' in s or 'TROPICAL' in s or 'STAWBERRY' in s or 'WATER' in s: return 'Velo'
        return 'BAT'
    return None


def detect_category(sku, supplier_id):
    s = sku.upper()
    if supplier_id == 1:
        if 'CUP SHUP' in s: return 'Beverages'
        if any(x in s for x in ['DBP', 'TBP', 'MPB']): return 'Banaspati Ghee'
        if any(x in s for x in ['POMACE', 'EXTRA VIRGIN', 'KALONJI']): return 'Cooking Oil'
        if any(x in s for x in ['OIL', 'DCO', 'TCO', 'CORN', 'COR OIL', 'TULLO']): return 'Cooking Oil'
        return 'Cooking Oil'
    if supplier_id == 2:
        if 'GHEE' in s or 'BUCKET' in s: return 'Banaspati Ghee'
        if 'OLIVOLA' in s: return 'Cooking Oil'
        if 'OIL' in s: return 'Cooking Oil'
        return 'Cooking Oil'
    if supplier_id == 3:
        if any(x in s for x in ['NIDO POWDER', 'BUNIYAD', 'NIDO SAN']): return 'Powder'
        if any(x in s for x in ['NIDO 1+', 'NIDO 3+']): return 'Nutrition'
        if any(x in s for x in ['LACTOGEN', 'LACTOGROW', 'PRENAN']): return 'Nutrition'
        if s.startswith('NAN ') or 'NAN ' in s: return 'Nutrition'
        if 'CERELAC' in s: return 'Baby Food'
        if any(x in s for x in ['KOKO', 'TRIX']): return 'Cereals'
        if 'EVERYDAY' in s and 'TEA' not in s: return 'Powder'
        if 'EVERYDAY TEA' in s: return 'Beverages'
        if 'MILO' in s and any(x in s for x in ['PWD', 'POWDER', 'ACTIVE GO', 'ALL IN ONE', 'CEREAL']): return 'Powder'
        if 'MILO' in s: return 'Beverages'
        if 'NESCAFE' in s and any(x in s for x in ['CLASSIC', 'GOLD', '3 IN', 'POUCH']): return 'Powder'
        if 'NESCAFE' in s and 'HAZULNET ICE' in s: return 'Powder'
        if 'NESCAFE' in s: return 'Beverages'
        if 'MILKPAK' in s: return 'Dairy'
        if 'CREAM' in s: return 'Dairy'
        if 'NESVITA' in s: return 'Dairy'
        if any(x in s for x in ['FRUITA', 'NESFRUITA', 'NFV']): return 'Beverages'
        if 'PURE LIFE' in s or 'SPARKE' in s: return 'Water'
        return 'Powder'
    if supplier_id == 4:
        if 'CREAM' in s: return 'Dairy'
        if 'FALVOUR' in s or 'FLAVOUR' in s: return 'Beverages'
        if 'TARANG' in s: return 'Beverages'
        if 'FCMP' in s: return 'Powder'
        if 'TARKA' in s: return 'Banaspati Ghee'
        if 'OMUNG' in s and 'DOBALA' in s: return 'Dairy'
        if 'OMUNG' in s or 'DAIRY' in s: return 'Dairy'
        if 'PROCAL' in s: return 'Dairy'
        return 'Dairy'
    if supplier_id == 5:
        if any(x in s for x in ['SURF', 'RIN', 'SUNLIGHT', 'COMFORT']): return 'Laundry Care'
        if 'DOMEX' in s: return 'Home Care'
        if 'VIM' in s: return 'Home Care'
        if any(x in s for x in ['LIFEBUOY', 'LIFEBOUY']):
            if any(x in s for x in ['SHMPOO', 'SHAMPOO', 'SMPOO']): return 'Hair Care'
            if 'HANDWASH' in s: return 'Personal Care'
            return 'Personal Care'
        if 'LUX' in s:
            if 'HANDWASH' in s or 'BODYWASH' in s: return 'Personal Care'
            return 'Personal Care'
        if 'FAIR' in s or 'LOVELY' in s: return 'Skin Care'
        if 'PONDS' in s or 'POND ' in s: return 'Skin Care'
        if any(x in s for x in ['DOVE', 'CLEAR', 'SUNSILK', 'TRESEME']):
            return 'Hair Care'
        if 'KNORR' in s: return 'Food'
        if 'RAFHAN' in s and 'OIL' in s: return 'Cooking Oil'
        if 'RAFHAN' in s: return 'Food'
        if 'GLAXOSE' in s or 'ENERGILE' in s: return 'Beverages'
        if 'HELLMANN' in s: return 'Food'
        return 'Personal Care'
    if supplier_id == 6:
        return 'Tea'
    if supplier_id == 7:
        if 'BANASPATI' in s or 'GHEE' in s or 'BUCKET' in s: return 'Banaspati Ghee'
        if 'OIL' in s or 'COOKING' in s: return 'Cooking Oil'
        if any(x in s for x in ['BASMATI', 'SELLA', 'DAGEE', 'BROKEN']): return 'Rice'
        if 'SALT' in s: return 'Spices & Condiments'
        return 'Cooking Oil'
    if supplier_id == 8:
        if any(x in s for x in ['CAKE', 'SOFT BAKE']): return 'Bakery'
        return 'Biscuits'
    if supplier_id == 9:
        if 'SALT' in s: return 'Spices & Condiments'
        if any(x in s for x in ['PEPPER', 'CHILLI POWDER', 'CORIANDER', 'CUMIN', 'CURRY',
                                  'GARAM MASALA', 'GARLIC POWDER', 'TURMERIC', 'GINGER',
                                  'KASURI', 'CHAAT MASALA', 'CHICKEN POWDER']): return 'Spices & Condiments'
        if s.startswith('RM ') or ' RM ' in s: return 'Recipe Mixes'
        if 'QUICK COOK' in s or 'HALEEM' in s: return 'Food'
        if 'PICKLE' in s or 'ACHAR' in s: return 'Pickles & Chutneys'
        if 'JAM' in s and 'JELLY' not in s: return 'Jams & Preserves'
        if 'CRYSTAL JELLY' in s: return 'Desserts'
        if any(x in s for x in ['KETCHUP', 'SAUCE', 'MAYO', 'CHAATNY', 'SRIRACHA', 'VINEGAR', 'SOYA']): return 'Sauces & Condiments'
        if any(x in s for x in ['CUSTARD', 'KHEER', 'PUDDING', 'JELLY']): return 'Desserts'
        if 'VERMICELLI' in s: return 'Food'
        return 'Food'
    if supplier_id == 10:
        if any(x in s for x in ['VELO', 'TROPICAL', 'STAWBERRY', 'WATER MILLEN']): return 'Nicotine Pouches'
        return 'Tobacco'
    return 'General'


def detect_pack_size(sku):
    s = sku.strip()
    patterns = [
        (r'(\d+(?:\.\d+)?)\s*(?:KG|kg|Kg)', 'kg'),
        (r'(\d+(?:\.\d+)?)\s*(?:G|g|gm|Gm|GM)(?![a-zA-Z])', 'g'),
        (r'(\d+(?:\.\d+)?)\s*(?:LTR|ltr|Ltr|Litr|lit|Lit|LIT)', 'L'),
        (r'(\d+(?:\.\d+)?)\s*L(?![a-zA-Z])', 'L'),
        (r'(\d+(?:\.\d+)?)\s*(?:ML|ml|Ml)', 'ml'),
    ]
    for pat, unit in patterns:
        matches = re.findall(pat, s)
        if matches:
            val = matches[-1]
            return f"{val}{unit}"
    return None


def detect_is_powder(sku, category):
    s = sku.upper()
    if category == 'Powder':
        return True
    if any(x in s for x in ['PWD', 'POWDER']):
        return True
    if any(x in s for x in ['CERELAC', 'CUSTARD', 'CORNFLOUR', 'CRNFLOUR']):
        return True
    return False


def generate_description(sku, brand, category, pack_size):
    parts = [brand] if brand else []
    parts.append(sku)
    if pack_size:
        parts.append(f"- {pack_size}")
    if category:
        parts.append(f"({category})")
    return ' '.join(parts) if len(parts) > 1 else sku


# ===== Process all rows =====
products = []
categories_data = []
seen_codes = {}

for r in rows:
    sku = r['sku']
    sid = r['supplier_id']
    clean_sku = sku[2:].strip() if sku.startswith('##') else sku

    brand = detect_brand(clean_sku, sid)
    category = detect_category(clean_sku, sid)
    pack_size = detect_pack_size(clean_sku)
    uom_id = 24       # Piece (default)
    sales_uom_id = 33  # Case (default)
    is_powder = detect_is_powder(clean_sku, category)
    desc = generate_description(clean_sku, brand, category, pack_size)

    code = sku
    if code in seen_codes:
        seen_codes[code] += 1
        code = f"{sku} [{seen_codes[code]}]"
    else:
        seen_codes[code] = 1

    product = {
        "product_code": code,
        "product_name": code,
        "description": desc,
        "supplier_id": sid,
        "brand": brand,
        "pack_size": pack_size,
        "uom_id": uom_id,
        "sales_uom_id": sales_uom_id,
        "uom_conversion_factor": r['units'],
        "cost_price": r['invoice_price'],
        "unit_sell_price": r['retail_price'],
        "valuation_method": "FIFO",
        "is_powder": is_powder,
        "is_active": True
    }
    products.append(product)
    categories_data.append((category, code))

# ===== Write sku.json =====
json_file = os.path.join(BASE_DIR, 'sku.json')
with open(json_file, 'w', encoding='utf-8') as f:
    json.dump(products, f, indent=2, ensure_ascii=False)

# ===== Write product_categories.csv =====
cat_file = os.path.join(BASE_DIR, 'product_categories.csv')
with open(cat_file, 'w', encoding='utf-8', newline='') as f:
    writer = csv.writer(f)
    writer.writerow(['Category', 'SKU'])
    for cat, sku in categories_data:
        writer.writerow([cat, sku])

# ===== Summary =====
print(f"\n✅ Generated sku.json with {len(products)} products")
print(f"✅ Generated product_categories.csv with {len(categories_data)} entries")

cat_counts = Counter(cat for cat, _ in categories_data)
print(f"\n📊 Category breakdown:")
for cat, count in sorted(cat_counts.items(), key=lambda x: -x[1]):
    print(f"  {cat}: {count} products")

sup_counts = Counter(p['supplier_id'] for p in products)
print(f"\n📊 Supplier breakdown:")
for sid, count in sorted(sup_counts.items()):
    print(f"  Supplier {sid}: {count} products")

brand_counts = Counter(p['brand'] for p in products if p['brand'])
print(f"\n📊 Brand breakdown:")
for brand, count in sorted(brand_counts.items(), key=lambda x: -x[1]):
    print(f"  {brand}: {count}")
