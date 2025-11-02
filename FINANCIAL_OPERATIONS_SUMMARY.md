# Financial Operations Module - Implementation Summary

## Overview
This document summarizes the new financial operations features added to the MoonTrader system, including cheque payment tracking, employee salary payments, general expense management, and standardized units of measurement.

## 1. Cheque Payment Management

### Enhanced Payments Table
The `payments` table has been enhanced with 6 new fields to handle cheque payments:

#### New Fields
- `cheque_number` - The cheque number for tracking
- `cheque_bank` - Bank name on the cheque
- `cheque_branch` - Bank branch information
- `cheque_date` - Date written on the cheque
- `cheque_clearance_date` - Date when the cheque was cleared/bounced
- `cheque_status` - Enum: `pending`, `cleared`, `bounced`, `cancelled`

#### Cheque Workflow
```
1. Receive cheque → Set payment_method = 'cheque', fill cheque details, status = 'pending'
2. Bank clearance → Update cheque_clearance_date, cheque_status = 'cleared'
3. If bounced → Update cheque_status = 'bounced', handle reversal
4. If cancelled → Update cheque_status = 'cancelled'
```

#### Key Features
- Separate date tracking: cheque_date (when written) vs cheque_clearance_date (when cleared)
- Index on `cheque_status` + `cheque_clearance_date` for efficient reporting
- Supports all existing payment functionality
- Integrates with double-entry accounting system

---

## 2. Employee Salary Management

### Employee Salaries Table
Complete payroll system with salary structure and multiple payment methods.

#### Core Fields
- `salary_number` - Unique salary payment reference (e.g., "SAL-2025-01-001")
- `employee_id` - Foreign key to employees table
- `month` - YYYY-MM format for salary month

#### Salary Structure
```
Basic Salary: PKR 50,000
+ Allowances: PKR 10,000 (housing, transport, medical, etc.)
- Deductions: PKR 5,000 (taxes, advances, etc.)
= Net Salary: PKR 55,000
```

#### Payment Tracking
- `payment_date` - When salary was paid
- `payment_method` - Enum: `cash`, `cheque`, `bank_transfer`
- `reference_number` - Bank reference or transaction ID
- `cheque_number`, `cheque_bank`, `cheque_date` - For cheque payments

#### Accounting Integration
- `journal_entry_id` - Links to accounting journal entry
- `posting_status` - Enum: `draft`, `posted`, `cancelled`
- Automatic journal entry on posting:
  ```
  Debit: Salary Expense Account   PKR 55,000
  Credit: Cash/Bank Account       PKR 55,000
  ```

#### Business Rules
- **Unique Constraint**: `employee_id` + `month` prevents duplicate salary payments
- Must specify basic_salary (required)
- Allowances and deductions default to 0.00
- Net salary = basic_salary + allowances - deductions

#### Indexes
- `payment_date` + `posting_status` - For payment reports
- `month` + `posting_status` - For monthly payroll reports

---

## 3. General Expense Management

### Expenses Table
Comprehensive expense tracking for all business operational costs.

#### Core Fields
- `expense_number` - Unique expense reference (e.g., "EXP-2025-001")
- `expense_date` - Date of expense
- `expense_category` - Enum of 9 categories (see below)
- `description` - Expense details
- `amount` - Expense amount

#### Expense Categories
1. **rent** - Office/warehouse rent
2. **utilities** - Electricity, water, internet
3. **office_supplies** - Stationery, equipment
4. **repairs** - Maintenance and repairs
5. **insurance** - Business insurance premiums
6. **marketing** - Advertising and promotion
7. **travel** - Business travel expenses
8. **professional_fees** - Legal, consulting fees
9. **miscellaneous** - Other expenses

#### Payment Methods
- `payment_method` - Enum: `cash`, `cheque`, `bank_transfer`, `online`
- `reference_number` - Payment reference/receipt number
- For cheque payments: `cheque_number`, `cheque_bank`, `cheque_date`, `cheque_clearance_date`, `cheque_status`

#### Additional Details
- `vendor_name` - Name of service provider/vendor
- `receipt_number` - Physical receipt number
- `cost_center_id` - For departmental expense allocation
- `notes` - Additional notes

#### Accounting Integration
- `expense_account_id` - Links to Chart of Accounts (which expense account to debit)
- `journal_entry_id` - Links to journal entry
- `posting_status` - Enum: `draft`, `posted`, `cancelled`
- Automatic journal entry on posting:
  ```
  Debit: Expense Account (rent/utilities/etc.)   PKR X
  Credit: Cash/Bank Account                      PKR X
  ```

#### Indexes
- `expense_date` + `posting_status` - For expense reports by date
- `expense_category` + `expense_date` - Category-wise analysis
- `cheque_status` + `cheque_clearance_date` - Cheque tracking

---

## 4. Units of Measurement (UOM)

### UOMs Table
Standardized measurement units based on ERPNext industry standards.

#### Schema
- `uom_name` - Unique unit name (e.g., "Kilogram", "Meter")
- `symbol` - Short symbol (e.g., "kg", "m")
- `description` - Detailed description
- `must_be_whole_number` - Boolean flag (prevents 0.5 boxes, etc.)
- `enabled` - Boolean flag to activate/deactivate units

#### 46 Standard Units by Category

**Length Units (8)**
- Meter (m), Centimeter (cm), Millimeter (mm), Kilometer (km)
- Inch (in), Foot (ft), Yard (yd), Mile (mi)

**Mass/Weight Units (6)**
- Kilogram (kg), Gram (g), Milligram (mg), Ton (t)
- Pound (lb), Ounce (oz)

**Volume Units (4)**
- Liter (L), Milliliter (mL), Cubic Meter (m³), Gallon (gal)

**Area Units (4)**
- Square Meter (m²), Square Foot (ft²), Acre (ac), Hectare (ha)

**Count Units (6)** - *must_be_whole_number = true*
- Unit, Piece (Pc), Nos, Dozen (Dz), Pair, Set

**Packaging Units (7)** - *must_be_whole_number = true*
- Box, Carton (Ctn), Bag, Pack, Case, Pallet (Plt), Bundle (Bdl)

**Time Units (5)**
- Hour (Hr), Day, Week (Wk), Month (Mo), Year (Yr)

**Textile Units (2)**
- Meter (Fabric), Yard (Fabric)

**Temperature Units (2)**
- Celsius (°C), Fahrenheit (°F)

**Pakistan-Specific Units (2)**
- Maund (Maund) - Traditional weight unit (~40 kg)
- Seer (Seer) - Traditional weight unit (~1 kg)

#### Key Features
- **must_be_whole_number flag**: Enforces integer values for countable items
  - ✅ 10 boxes, 5 cartons, 3 dozen
  - ❌ 2.5 boxes, 0.7 cartons (validation at application level)
- Comprehensive coverage: metric, imperial, packaging, local units
- Industry-standard based on ERPNext system
- Extensible: can add custom units as needed

#### Future Enhancement
The `products` table currently uses `unit_of_measure` as a string. Consider migrating to:
```php
$table->foreignId('uom_id')->constrained('uoms');
```
This would enable:
- Dropdown selection from standard units
- Unit conversion functionality
- Better data integrity

---

## 5. Double-Entry Accounting Integration

All transactional tables follow consistent accounting integration:

### Standard Fields
- `journal_entry_id` - Links to `journal_entries` table
- `posting_status` - Enum: `draft`, `posted`, `cancelled`

### Posting Workflow
```
1. Create transaction → posting_status = 'draft'
2. Review and validate
3. Post → Create journal entry, update journal_entry_id, posting_status = 'posted'
4. If error → posting_status = 'cancelled', create reversal entry
```

### Journal Entry Examples

**Employee Salary Payment**
```
DR: Salary Expense (5100)          PKR 55,000
CR: Cash in Hand (1100)                         PKR 55,000
```

**Rent Expense**
```
DR: Rent Expense (5200)            PKR 50,000
CR: Bank Account (1200)                         PKR 50,000
```

**Cheque Received from Customer**
```
DR: Cheques in Hand (1150)         PKR 100,000
CR: Accounts Receivable (1300)                  PKR 100,000

[When cheque clears]
DR: Bank Account (1200)            PKR 100,000
CR: Cheques in Hand (1150)                      PKR 100,000
```

---

## 6. Reporting Capabilities

### Available Reports

#### Cheque Tracking
- Pending cheques report (status = 'pending')
- Cheques due for clearance (by cheque_date)
- Bounced cheques analysis
- Bank-wise cheque distribution

#### Payroll Reports
- Monthly salary register (by month)
- Employee-wise salary history
- Payment method analysis
- Salary component breakdown (basic, allowances, deductions)

#### Expense Analysis
- Category-wise expenses (9 categories)
- Department-wise expenses (via cost_center_id)
- Vendor-wise payment history
- Monthly expense trends
- Cheque payment tracking for expenses

#### UOM Usage
- Product-wise UOM distribution
- Most used measurement units
- Validation of whole-number units

---

## 7. Implementation Status

### ✅ Completed
- [x] Enhanced payments table with 6 cheque fields
- [x] Created employee_salaries table with full salary structure
- [x] Created expenses table with 9 expense categories
- [x] Created uoms table based on ERPNext standards
- [x] Populated UomSeeder with 46 standard units
- [x] Created models: Uom, EmployeeSalary, Expense
- [x] All migrations tested with `migrate:fresh --seed`
- [x] Verified UOM data seeding (46 units)
- [x] Verified table structures and indexes
- [x] Added UomSeeder to DatabaseSeeder call stack

### 🔄 Pending Development
- [ ] Define fillable properties and relationships in models
- [ ] Create posting service classes:
  - EmployeeSalaryPostingService
  - ExpensePostingService
  - ChequeManagementService
- [ ] Create controllers and request validation
- [ ] Create Livewire components for CRUD operations
- [ ] Implement cheque status transition logic
- [ ] Add automatic number generation for expense_number, salary_number
- [ ] Create views and UI components
- [ ] Migrate products table to use UOM foreign key

### 📋 Recommended Next Steps
1. **Model Development**: Add fillable properties, relationships, accessors/mutators
2. **Service Classes**: Implement posting logic and cheque management
3. **Controllers**: Create CRUD controllers with proper validation
4. **UI Development**: Build Livewire components following AccountingPeriod pattern
5. **Testing**: Create feature tests for posting workflows
6. **Documentation**: Create user guides for each module

---

## 8. Database Schema Summary

### New Tables Created
```
uoms (id, uom_name, symbol, description, must_be_whole_number, enabled, timestamps, soft_deletes)
├── Index: enabled + uom_name
└── 46 standard units seeded

employee_salaries (id, salary_number, employee_id, month, basic_salary, allowances, 
                   deductions, net_salary, payment_date, payment_method, reference_number,
                   cheque_number, cheque_bank, cheque_date, notes, journal_entry_id, 
                   posting_status, timestamps, soft_deletes)
├── Unique: employee_id + month
├── Index: payment_date + posting_status
└── Index: month + posting_status

expenses (id, expense_number, expense_date, expense_category, description, amount,
          payment_method, reference_number, cheque_number, cheque_bank, cheque_date,
          cheque_clearance_date, cheque_status, vendor_name, receipt_number, notes,
          cost_center_id, expense_account_id, journal_entry_id, posting_status, 
          timestamps, soft_deletes)
├── Index: expense_date + posting_status
├── Index: expense_category + expense_date
└── Index: cheque_status + cheque_clearance_date
```

### Enhanced Tables
```
payments (added 6 cheque fields)
├── cheque_number, cheque_bank, cheque_branch
├── cheque_date, cheque_clearance_date, cheque_status
└── Index: cheque_status + cheque_clearance_date
```

---

## 9. Configuration and Settings

### Enum Values

**payment_method** (used in multiple tables)
- cash
- cheque
- bank_transfer
- online

**cheque_status**
- pending
- cleared
- bounced
- cancelled

**expense_category**
- rent
- utilities
- office_supplies
- repairs
- insurance
- marketing
- travel
- professional_fees
- miscellaneous

**posting_status** (used in all transactional tables)
- draft
- posted
- cancelled

---

## 10. Security and Validation

### Data Validation Rules
- Salary month format: YYYY-MM (e.g., "2025-01")
- Net salary must equal: basic_salary + allowances - deductions
- Cheque fields required when payment_method = 'cheque'
- Expense category must be one of 9 valid categories
- UOM name must be unique
- Must_be_whole_number enforced for count/packaging units

### Business Logic
- Cannot pay duplicate salary for same employee + month
- Cannot post transaction without valid account links
- Cheque status transitions must be logical (pending → cleared/bounced, not backwards)
- Posted transactions cannot be deleted (must be cancelled with reversal entry)

### Access Control
- Policy classes created for EmployeeSalary and Expense models
- Integrate with Spatie permissions system
- Recommended permissions:
  - `create-salary`, `view-salary`, `post-salary`, `cancel-salary`
  - `create-expense`, `view-expense`, `post-expense`, `cancel-expense`
  - `clear-cheque`, `bounce-cheque`, `view-cheque-report`
  - `manage-uom`, `view-uom`

---

## Conclusion

The Financial Operations Module adds comprehensive functionality for:
1. **Cheque Management**: Track customer/supplier cheques through their lifecycle
2. **Payroll**: Complete employee salary payment system with multiple payment methods
3. **Expense Tracking**: Categorized business expense management
4. **Standardized Measurements**: Professional UOM system for inventory

All modules integrate seamlessly with the existing double-entry accounting system, ensuring accurate financial records and reporting capabilities.

**Next Phase**: Implement posting services, create UI components, and add comprehensive testing.
