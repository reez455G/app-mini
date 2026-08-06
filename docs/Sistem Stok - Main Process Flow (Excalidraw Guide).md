---
title: Sistem Stok - Main Process Flow
description: Visual guide untuk membuat Excalidraw diagram
date: 2026-08-03
---

# 📊 SISTEM STOK - MAIN PROCESS FLOW

## **COMPREHENSIVE BUSINESS PROCESS DIAGRAM**

```
╔════════════════════════════════════════════════════════════════════╗
║              SISTEM STOK TOKO - COMPLETE WORKFLOW                 ║
╚════════════════════════════════════════════════════════════════════╝

┌──────────────────────────────────────────────────────────────────┐
│                    1️⃣  INITIALIZATION (Setup)                    │
│                      Owner Access Only                           │
└──────────────────────────────────────────────────────────────────┘
         │
    ┌────┼────┬────────┬──────────┐
    ▼    ▼    ▼        ▼          ▼
 ┌────────────────────────────────────────┐
 │  • Customer Master Data                │
 │  • Supplier Master Data                │
 │  • User Accounts (Owner/Karyawan)      │
 │  • Product Categories                  │
 │    (BAN, OLI, AKI, SPAREPART)          │
 └────────────────────────────────────────┘
              │
              ▼
    ┌──────────────────────────┐
    │  ✅ System Ready         │
    └──────────────────────────┘
              │
              ├─────────────────┬───────────────────┐
              │                 │                   │
              ▼                 ▼                   ▼
        ┌───────────┐     ┌───────────┐     ┌──────────────┐
        │PURCHASING │     │INVENTORY  │     │   SALES      │
        │   PHASE   │     │ MGMT      │     │  TRANSACTION │
        └───────────┘     └───────────┘     └──────────────┘
              │                 │                   │
              └─────────────────┴───────────────────┘
                          │
                          ▼
                  ┌─────────────────┐
                  │   REPORTING &   │
                  │   ANALYTICS     │
                  └─────────────────┘


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│             2️⃣  PURCHASING PHASE (Input Pembelian)               │
│                    Owner Access Only                             │
└──────────────────────────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  INPUT PEMBELIAN BARANG             │
    │  ─────────────────────────          │
    │  □ Select Supplier (dropdown)       │
    │  □ Invoice Number                   │
    │  □ Invoice Date                     │
    │  □ Due Date (if TOP)                │
    │  □ Payment Type (TOP/CASH)          │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  ADD ITEMS (Line Items)             │
    │  ─────────────────────              │
    │  FOR EACH ITEM:                     │
    │  └─ Category (BAN/OLI/AKI/SP)      │
    │  └─ Product Code                    │
    │  └─ Product Name                    │
    │  └─ Invoice Price                   │
    │  └─ Net Price (Flexible)            │
    │  └─ Quantity                        │
    │  └─ Subtotal (auto-calc)            │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  SAVE PURCHASE DOCUMENT             │
    │  Status: PENDING                    │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  UPDATE DATA BARANG INVENTORY       │
    │  └─ Add/Update stock levels         │
    │  └─ Link product to supplier        │
    │  └─ Store pricing tiers             │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  ✅ Purchase Document Created       │
    │  ✅ Inventory Updated               │
    └─────────────────────────────────────┘


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│          3️⃣  INVENTORY MANAGEMENT (Data Barang)                  │
│                    Owner Access Only                             │
│                  Multi-Supplier Support                          │
└──────────────────────────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  PRODUCT MASTER (Per Product)       │
    │  ─────────────────────────          │
    │  • Product Code (Unique)            │
    │  • Product Name                     │
    │  • Category                         │
    │  • Total Stock QTY (all suppliers)  │
    │  • Status (Active/Inactive)         │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  SUPPLIER PRICING                   │
    │  (Multiple records per product)     │
    │  ─────────────────────────          │
    │  FOR EACH SUPPLIER:                 │
    │  • Supplier Name (Link)             │
    │  • Invoice Price (Cost)             │
    │  • Net Price (Flexible/Supplier)    │
    │  • Last Purchase Date               │
    │  • Supplier Status                  │
    │                                     │
    │  Example: Ban Bridgestone A/T       │
    │  ├─ Supplier A: Rp 100.000          │
    │  ├─ Supplier B: Rp 95.000           │
    │  └─ Supplier C: Rp 98.000           │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  GLOBAL SELLING PRICES              │
    │  (Same for all suppliers)           │
    │  ─────────────────────────          │
    │  • ECER Price (1-5 pcs)             │
    │  • BENGKEL Price (6-10 pcs)         │
    │  • GROSIR Price (11-100 pcs)        │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  AVAILABLE OPERATIONS:              │
    │  □ View all products                │
    │  □ Search/Filter by category        │
    │  □ Search by supplier               │
    │  □ Compare prices (all suppliers)   │
    │  □ Update selling prices (global)   │
    │  □ Update supplier costs/prices     │
    │  □ Adjust stock (all sources)       │
    │  □ View purchase history (per sup)  │
    └─────────────────────────────────────┘


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│         4️⃣  SALES TRANSACTION (Transaksi Penjualan)              │
│              Owner & Karyawan Access                             │
└──────────────────────────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  START NEW SALES TRANSACTION        │
    │  ─────────────────────────         │
    │  □ Select/Add Customer              │
    │  □ Generate Invoice #               │
    │  □ Set Invoice Date                 │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  ADD ITEMS TO INVOICE               │
    │  ─────────────────────────         │
    │  FOR EACH ITEM:                     │
    │  ├─ Search/Scan Barcode             │
    │  ├─ Select Product                  │
    │  ├─ Enter Quantity                  │
    │  │                                  │
    │  ▼──► *** AUTO-DETECT PRICE *** ◄──┤
    │  │                                  │
    │  │  IF Qty = 1-5    → ECER Price   │
    │  │  IF Qty = 6-10   → BENGKEL      │
    │  │  IF Qty = 11+    → GROSIR       │
    │  │                                  │
    │  ├─ Show Applied Price              │
    │  ├─ Calculate Subtotal              │
    │  └─ Add to invoice                  │
    │                                     │
    │  [Continue adding more items...]    │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  TRANSACTION SUMMARY                │
    │  ─────────────────────────         │
    │  • Total Items: X                   │
    │  • Total Quantity: X pcs            │
    │  • Grand Total: Rp XXX.XXX          │
    │  • Amount Paid: Rp XXX              │
    │  • Kembalian: Rp XXX (change)       │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  PAYMENT INFO                       │
    │  ─────────────────────────         │
    │  □ Payment Method (TUNAI/TRANSFER)  │
    │  □ Amount Paid                      │
    │  □ Calculate Change                 │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  GENERATE INVOICE                   │
    │  ─────────────────────────         │
    │  ✅ Customer Name                   │
    │  ✅ Invoice #                       │
    │  ✅ Date                            │
    │  ✅ Item Names ONLY (no suppliers)  │
    │  ✅ Qty × Unit Price per item       │
    │  ✅ Total Amount                    │
    │  ✅ Payment Method                  │
    │  ✅ Change (if cash)                │
    │  ✅ Optional: Profit info (internal)│
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  SAVE TRANSACTION                   │
    │  └─ Log in TRANSAKSI PENJUALAN     │
    │  └─ Record customer info            │
    │  └─ Record payment details          │
    │  └─ Timestamp transaction           │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  UPDATE INVENTORY (AUTO)            │
    │  ─────────────────────────         │
    │  FOR EACH ITEM SOLD:                │
    │  └─ Decrement stock quantity        │
    │  └─ Flag if low stock               │
    │  └─ Trigger reorder alert (if set)  │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  ✅ SALES COMPLETED                 │
    │  ✅ Invoice Ready to Print/Send      │
    │  ✅ Stock Updated                   │
    └─────────────────────────────────────┘


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│           5️⃣  STOCK REPORTING (Laporan Stok)                     │
│              Owner & Karyawan Access                             │
└──────────────────────────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  VIEW CURRENT INVENTORY             │
    │  ─────────────────────────         │
    │  List view with:                    │
    │  • Product Code                     │
    │  • Product Name                     │
    │  • Category                         │
    │  • Current Stock (PCS)              │
    │  • Pricing (ECER/BENGKEL/GROSIR)   │
    │  • Supplier Name                    │
    │  • Stock Status (OK/LOW/CRITICAL)   │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  FILTER & SEARCH OPTIONS            │
    │  ─────────────────────────         │
    │  □ By Category                      │
    │  □ By Supplier                      │
    │  □ By Stock Level (< X pcs)         │
    │  □ By Price Range                   │
    │  □ Search by Name/Code              │
    └─────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │  EXPORT OPTIONS                     │
    │  ─────────────────────────         │
    │  □ Export to PDF                    │
    │  □ Export to Excel                  │
    │  □ Print Report                     │
    └─────────────────────────────────────┘


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│              6️⃣  COMPREHENSIVE REPORTING (Laporan)               │
│                    Owner Access Only                             │
└──────────────────────────────────────────────────────────────────┘
         │
    ┌────┼────┬───────┬──────────┐
    ▼    ▼    ▼       ▼          ▼


A) LAPORAN PEMBELIAN (Purchase Report)
   ─────────────────────────────────────
   Date Range: [From] ──► [To]
   │
   ├─► PER SUPPLIER BREAKDOWN
   │   └─ Supplier Name
   │   └─ Invoice #
   │   └─ Purchase Amount (Rp)
   │   └─ Items Qty
   │
   └─► SUMMARY TOTALS
       └─ Total Purchase Value
       └─ Total Items Purchased
       └─ Average Invoice Value


B) LAPORAN PENJUALAN (Sales Report)
   ─────────────────────────────────
   Date Range: [From] ──► [To]
   │
   ├─► PER CUSTOMER BREAKDOWN
   │   └─ Customer Name
   │   └─ Invoice #
   │   └─ Sale Amount (Rp)
   │   └─ Items Qty
   │   └─ Transaction Count
   │
   └─► SUMMARY TOTALS
       └─ Total Sales Revenue
       └─ Total Items Sold
       └─ Average Sale Value
       └─ Total Transactions


C) LAPORAN LABA (Profit/Loss Report)
   ──────────────────────────────────
   Date Range: [From] ──► [To]
   │
   ├─► SUMMARY METRICS
   │   └─ Total Revenue (from sales)
   │   └─ Total Cost (from purchases)
   │   └─ Gross Profit (Revenue - Cost)
   │   └─ Profit Margin (%)
   │
   ├─► PER TRANSACTION DETAIL
   │   └─ Invoice #
   │   └─ Profit per Item
   │   └─ Profit per Transaction
   │   └─ Profit Margin %
   │
   └─► PER PRODUCT ANALYSIS
       └─ Most Profitable Items
       └─ Profit Contribution
       └─ Profit per Category


D) LAPORAN STOK LARIS (Top Sellers)
   ────────────────────────────────
   Date Range: [From] ──► [To]
   │
   ├─► RANKED BY QUANTITY
   │   └─ Item #1: X pcs sold (Rp XXX total)
   │   └─ Item #2: X pcs sold
   │   └─ Item #3: X pcs sold
   │   ...
   │
   ├─► RANKED BY REVENUE
   │   └─ Item #1: Rp XXX.XXX generated
   │   └─ Item #2: Rp XXX.XXX
   │   ...
   │
   ├─► RANKED BY PROFIT
   │   └─ Item #1: Rp XXX profit
   │   └─ Item #2: Rp XXX profit
   │   ...
   │
   └─► RANKING BY CATEGORY
       └─ Best-selling category
       └─ Most profitable category
       └─ Highest turnover category


E) LAPORAN GLOBAL (Dashboard Summary)
   ───────────────────────────────────
   Date Range: [From] ──► [To]
   │
   ├─► KEY PERFORMANCE INDICATORS
   │   ├─ Total Revenue (Rp): XXX.XXX
   │   ├─ Total Cost (Rp): XXX.XXX
   │   ├─ Net Profit (Rp): XXX.XXX
   │   ├─ Profit Margin (%): XX%
   │   ├─ ROI (%): XX%
   │   │
   │   ├─ Transactions Count: X
   │   ├─ Average Sale Value: Rp XXX
   │   ├─ Customers Served: X unique
   │   ├─ Items Sold (total pcs): X
   │   │
   │   ├─ Purchase Transactions: X
   │   ├─ Suppliers Used: X active
   │   └─ Average Purchase Value: Rp XXX
   │
   ├─► MONTHLY/WEEKLY TREND
   │   └─ Revenue trend chart
   │   └─ Profit trend chart
   │   └─ Volume trend
   │
   └─► VISUAL DASHBOARD
       ├─ Revenue vs Cost chart
       ├─ Profit by Category pie
       ├─ Top 5 Products bar
       └─ Timeline chart


F) LAPORAN RETUR (Return Report)
   ─────────────────────────────
   Date Range: [From] ──► [To]
   │
   ├─► RETUR PEMBELIAN (From Suppliers)
   │   └─ Supplier Name
   │   └─ Return Date
   │   └─ Original Invoice #
   │   └─ Kode Barang
   │   └─ Nama Barang
   │   └─ Qty returned
   │   └─ Reason
   │
   └─► RETUR PENJUALAN (From Customers)
       └─ Customer Name
       └─ Return Date
       └─ Original Invoice #
       └─ Kode Barang
       └─ Nama Barang
       └─ Qty returned
       └─ Reason
       └─ Refund/Credit processed


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│                 7️⃣  RETURN MANAGEMENT (Retur)                    │
│              Owner & Karyawan Access (partial)                   │
└──────────────────────────────────────────────────────────────────┘
         │
    ┌────┴─────────────────────────┐
    ▼                               ▼


A) RETUR PEMBELIAN (Purchase Return - Owner Only)
   ───────────────────────────────────────────────
   □ Original Supplier (dropdown)
   □ Original Invoice #
   □ Return Date
   │
   FOR EACH ITEM:
   ├─ Product Code
   ├─ Product Name
   ├─ Quantity Returned
   └─ Reason (Quality/Defect/Wrong/etc)
   │
   ▼
   UPDATE INVENTORY:
   ├─ Restock qty (add back)
   ├─ Adjust supplier credit
   └─ Log return transaction
   │
   ✅ RETUR PEMBELIAN logged


B) RETUR PENJUALAN (Sales Return - Owner & Karyawan)
   ─────────────────────────────────────────────────
   □ Customer (dropdown)
   □ Original Invoice #
   □ Return Date
   │
   FOR EACH ITEM:
   ├─ Product Code
   ├─ Product Name
   ├─ Quantity Returned
   └─ Reason (Quality/Defect/Wrong/etc)
   │
   ▼
   PROCESS RETURN:
   ├─ Calculate refund amount
   ├─ Restock qty (add back)
   ├─ Update customer balance/credit
   └─ Log return transaction
   │
   ✅ RETUR PENJUALAN logged
   ✅ Inventory Restored
   ✅ Credit Processed


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│                   🔐 ACCESS CONTROL SUMMARY                       │
└──────────────────────────────────────────────────────────────────┘

Feature                           │  Owner  │  Karyawan
──────────────────────────────────┼─────────┼──────────
Master Data Setup                 │   ✅    │    ❌
Input Pembelian                   │   ✅    │    ❌
Data Barang (Product Master)      │   ✅    │    ❌
Transaksi Penjualan (Input)       │   ✅    │    ✅
Laporan Stok (View)               │   ✅    │    ✅
Laporan Pembelian                 │   ✅    │    ❌
Laporan Penjualan (View)          │   ✅    │    ✅
Laporan Laba/Profit               │   ✅    │    ❌
Laporan Global/Dashboard          │   ✅    │    ❌
Retur Pembelian                   │   ✅    │    ❌
Retur Penjualan (Input)           │   ✅    │    ✅


════════════════════════════════════════════════════════════════════

┌──────────────────────────────────────────────────────────────────┐
│              🔄 AUTO-DETECTION LOGIC (KEY FEATURE)                │
└──────────────────────────────────────────────────────────────────┘

When customer enters QUANTITY in sales transaction:

   QTY: 1-5      ──►  APPLY ECER PRICE
        │
        │  (Retail/Individual)
        │
        
   QTY: 6-10     ──►  APPLY BENGKEL PRICE
        │
        │  (Workshop/Small bulk)
        │
        
   QTY: 11-100   ──►  APPLY GROSIR PRICE
        │
        │  (Wholesale/Large bulk)
        │
   
   System automatically selects correct price tier
   No manual selection needed
   Faster transaction processing
   Consistent pricing


════════════════════════════════════════════════════════════════════
```

---

## 📋 EXCALIDRAW DIAGRAM ELEMENTS

When recreating in Excalidraw, use these elements:

### **Box Colors:**
- 🔵 **Blue boxes**: Main processes/sections
- 🟢 **Green boxes**: Optional/Conditional
- 🔴 **Red boxes**: Critical decision points
- ⚪ **White boxes**: Data/Information

### **Flow Connectors:**
- **→** : Normal flow
- **⇄** : Two-way relationship
- **⬇** : Downward flow
- **◄►** : Bidirectional

### **Symbols:**
- ✅ : Completion/Success
- ⚠️ : Warning/Alert
- 🔐 : Security/Access control
- 📊 : Reports/Analytics
- 💾 : Data storage

---

## 🎯 MAIN WORKFLOWS (High-Level)

```
WORKFLOW 1: Purchase → Stock → Sell → Report
┌─────────┐  ┌──────┐  ┌────┐  ┌────────┐
│Purchase │→ │Stock │→ │Sell│→ │ Report │
└─────────┘  └──────┘  └────┘  └────────┘

WORKFLOW 2: Sales with Auto-Detection
┌──────────┐  ┌──────────────┐  ┌──────────┐  ┌──────────┐
│ Customer │→ │ Qty Entered  │→ │ Price    │→ │ Invoice  │
│ Selected │  │ 1-5/6-10/11+ │  │ Applied  │  │ Created  │
└──────────┘  └──────────────┘  └──────────┘  └──────────┘

WORKFLOW 3: Returns Management
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐
│ Return   │→ │ Process  │→ │ Restock  │→ │ Credit  │
│ Initiated│  │ Items    │  │ Inventory│  │ Applied │
└──────────┘  └──────────┘  └──────────┘  └─────────┘

WORKFLOW 4: Reporting & Analytics
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐
│ Select   │→ │ Generate │→ │ Analyze  │→ │ Export  │
│ Date     │  │ Report   │  │ Metrics  │  │ Results │
│ Range    │  │          │  │          │  │         │
└──────────┘  └──────────┘  └──────────┘  └─────────┘
```

---

**Created:** 2026-08-03
**Status:** Ready for Excalidraw implementation
**File Format:** Can be recreated as .excalidraw.md in Obsidian
