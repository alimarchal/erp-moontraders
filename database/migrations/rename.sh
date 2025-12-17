#!/bin/bash
cd /Users/alirazamarchal/Herd/moontrader/database/migrations

# Reorganize vehicle/van migrations (101510-101511)
python3 << 'PYTHON'
import os

os.rename('2025_11_11_101511_create_vehicle_stock_locations_table.php', 
          '2025_11_11_101510_create_vehicle_stock_locations_table.php')

os.rename('2025_11_11_101518_create_van_stock_balances_table.php',
          '2025_11_11_101511_create_van_stock_balances_table.php')

# Stock Adjustments & Transfers (101520-101523)
os.rename('2025_11_11_101519_create_stock_adjustments_table.php',
          '2025_11_11_101520_create_stock_adjustments_table.php')

os.rename('2025_11_11_101520_create_stock_adjustment_items_table.php',
          '2025_11_11_101521_create_stock_adjustment_items_table.php')

os.rename('2025_11_11_101521_create_stock_transfers_table.php',
          '2025_11_11_101522_create_stock_transfers_table.php')

os.rename('2025_11_11_101522_create_stock_transfer_items_table.php',
          '2025_11_11_101523_create_stock_transfer_items_table.php')

print("✓ All migrations reorganized successfully")
PYTHON
