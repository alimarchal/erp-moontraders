# Bonus Quantity Promotions (11+1 Type)

## Overview
The system now supports **"Buy X Get Y Free"** promotions (e.g., 11+1, 5+1, etc.) where customers receive bonus/free units when purchasing a specific quantity.

## Database Changes

### New Fields in `promotional_campaigns` Table:
- `discount_type`: ENUM now includes **'buy_x_get_y'** option
- `buy_quantity`: Number of units customer must purchase (e.g., 11)
- `get_quantity`: Number of free units customer receives (e.g., 1)

### Migration Applied:
```bash
php artisan migrate
# Applied: 2025_11_10_113409_add_bonus_quantity_to_promotional_campaigns_table
```

## How to Create "11+1" Campaigns

### Via Web Interface:
1. Navigate to **Campaigns** → **New Campaign**
2. Fill in basic details (Code, Name, Dates)
3. Select **Promotion Type**: "Buy X Get Y Free (e.g., 11+1)"
4. Enter:
   - **Buy Quantity**: 11
   - **Get Free Quantity**: 1
5. Save

### Via Code:
```php
$campaign = PromotionalCampaign::create([
    'campaign_code' => 'MILK-11+1',
    'campaign_name' => 'Milkpak UHT 11+1 Promo',
    'description' => 'Buy 11 packs, get 1 free',
    'start_date' => '2025-11-01',
    'end_date' => '2025-12-31',
    'discount_type' => 'buy_x_get_y',
    'buy_quantity' => 11,
    'get_quantity' => 1,
    'is_active' => true,
]);
```

## How It Works in GRN

### Scenario: Supplier Invoice with 11+1 Promotion

**Example**: Milkpak UHT 12x1000ml (11+1 Promo)

1. **Create GRN** with promotional campaign selected
2. **Supplier Invoice** shows:
   - Product: Milkpak UHT 1000ml
   - Quantity on invoice: **12 packs** (11 paid + 1 free)
   - Total cost: Cost for **11 packs only**

3. **GRN Entry**:
   - Select Campaign: "MILK-11+1"
   - **Qty Received**: 12 (physical quantity received)
   - **Qty Accepted**: 12 (if all good quality)
   - **Unit Cost**: Cost per pack for the 11 paid units
   - System tracks that 1 unit was free bonus

### Stock & Accounting Impact:

#### Stock Movement:
- **Physical Stock**: +12 units added to warehouse
- **Batch marked as promotional**: Priority selling applied
- **FIFO/Priority**: Promotional batches sold first (if priority_order set)

#### Cost Accounting:
- **Total Cost**: Calculated for 11 units only
- **Unit Cost**: Spread across actual paid quantity
- **Bonus Unit**: Cost absorbed into the batch (no separate free unit)

Example:
```
Supplier charges Rs. 850 per pack
Invoice Total: Rs. 9,350 (11 × Rs. 850)
Received: 12 packs

Unit Cost = Rs. 9,350 ÷ 11 = Rs. 850
Total Stock Value: Rs. 9,350 (for 12 units)
Effective cost per unit: Rs. 779.17 (Rs. 9,350 ÷ 12)
```

## Display in System

### Campaign List:
- Shows badge: **"11+1"** in orange for buy_x_get_y campaigns
- Other types show: "10% Off", "Rs. 500 Off", or "Special Price"

### GRN Campaign Dropdown:
- Shows: "MILK-11+1 - Milkpak UHT 11+1 Promo **(11+1)**"
- Clearly indicates promotion type

## Common Use Cases

### 1. Standard 11+1 Promotion
- Buy: 11 units
- Get: 1 unit free
- Total received: 12 units
- **Pay for**: 11 units

### 2. 5+1 Promotion
- Buy: 5 units
- Get: 1 unit free
- Total received: 6 units
- **Pay for**: 5 units

### 3. 23+1 Promotion (Larger packs)
- Buy: 23 units
- Get: 1 unit free
- Total received: 24 units (one carton of 24)
- **Pay for**: 23 units

### 4. 2+1 Promotion (Heavy discount)
- Buy: 2 units
- Get: 1 unit free
- Total received: 3 units
- **Pay for**: 2 units
- Equivalent to: 33.33% discount

## Available Promotion Types

| Type | Description | Example |
|------|-------------|---------|
| `percentage` | Percentage discount | 10% off |
| `fixed_amount` | Fixed amount off | Rs. 500 off |
| `special_price` | Special promotional price | Rs. 1,200 (was Rs. 1,500) |
| `buy_x_get_y` | Buy X units, get Y free | 11+1 (buy 11, get 1 free) |

## Important Notes

1. **Quantity Tracking**: Always enter the **total quantity received** (including free units)
2. **Cost Entry**: Enter the cost based on **paid units only**
3. **Stock Valuation**: System handles cost allocation automatically
4. **Priority Selling**: Promotional batches can be prioritized in FIFO
5. **Batch Tracking**: Each GRN line with promotion creates a tracked batch

## Validation Rules

- `buy_quantity`: Required if discount_type is 'buy_x_get_y', minimum 1
- `get_quantity`: Required if discount_type is 'buy_x_get_y', minimum 1
- Both must be whole numbers (no decimals for bonus promotions)
- Campaign must be active and within date range to appear in GRN

## Testing

Test campaign created:
```
Code: MILK-11+1
Name: Milkpak UHT 11+1 Promo
Type: buy_x_get_y
Buy: 11 units
Get: 1 unit free
Status: Active
```

## Future Enhancements

Possible additions:
- [ ] Multi-tier promotions (Buy 11+1, Buy 23+2, etc.)
- [ ] Product-specific quantity rules
- [ ] Automatic unit cost calculation helper
- [ ] Promotion effectiveness reports
- [ ] Customer-facing promotion labels

## Support

For questions about bonus quantity promotions, contact the development team or refer to this documentation.

---
**Last Updated**: November 10, 2025
**Migration**: 2025_11_10_113409_add_bonus_quantity_to_promotional_campaigns_table
