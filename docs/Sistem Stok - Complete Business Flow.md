---
title: Sistem Stok - Complete Business Flow
date: 2026-08-03
type: system-documentation
---

# 🏪 Sistem Stok Toko - Complete Business Flow

## Ringkasan Sistem
Sistem manajemen stok offline untuk toko parts dengan fitur:
- ✅ Multi-supplier & multi-user
- ✅ Dynamic pricing (ECER/BENGKEL/GROSIR)
- ✅ Auto-detection purchase type based on quantity
- ✅ Comprehensive reporting & profit tracking
- ✅ Offline-first dengan sync capability

---

## 1️⃣ INITIALIZATION PHASE (Setup - Owner Only)

```
┌─────────────────────────────────────────┐
│     OWNER SETUP - Master Data Entry     │
└─────────────────────────────────────────┘
         │
    ┌────┴─────┬─────────┬──────────┐
    ▼          ▼         ▼          ▼
┌────────┐ ┌────────┐ ┌─────┐ ┌──────────┐
│Customer│ │Supplier│ │User │ │Categories│
│  Data  │ │  Data  │ │Mgmt │ │(BAN,OLI, │
└────────┘ └────────┘ └─────┘ │AKI,SPARE)│
                               └──────────┘
     │         │          │          │
     └─────────┴──────────┴──────────┘
              │
              ▼
      ✅ Database Ready
```

### Data Master:
- **Pelanggan**: Kode, Nama, Alamat, No HP
- **Supplier**: Kode, Nama, Alamat, No HP
- **User**: Account dengan role (Owner/Karyawan)
- **Kategori**: BAN, OLI, AKI, SPAREPART

---

## 2️⃣ PURCHASING PHASE (Input Pembelian - Owner Only)

```
┌─────────────────────────────────────────┐
│    INPUT PEMBELIAN BARANG (Owner)       │
└─────────────────────────────────────────┘
         │
    ┌────┴─────────────────────────────┐
    ▼                                   ▼
┌─────────────────┐          ┌─────────────────┐
│ Enter Supplier  │          │ Payment Method  │
│ & Invoice Data  │          │ (TOP / CASH)    │
└─────────────────┘          └─────────────────┘
    │                                   │
    └────┬────────────────────────────┬─┘
         │                            │
         ▼                            ▼
    ┌─────────────────────┐    ┌──────────────┐
    │  Add Product Items  │    │ Payment Terms│
    │  - Kode Barang      │    │ - Due Date   │
    │  - Nama Barang      │    │ - TOP Period │
    │  - Kategori         │    └──────────────┘
    │  - Qty              │
    │  - Harga Faktur     │
    │  - Harga Netto      │
    │  - Total            │
    └─────────────────────┘
         │
         ▼
    ┌──────────────────────────────┐
    │ Save Purchase Document       │
    │ Status: PENDING              │
    └──────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────┐
    │ Update DATA BARANG (Stock)   │
    │ - Add to Inventory           │
    │ - Link to Supplier           │
    └──────────────────────────────┘
```

### Input Fields:
```
Nomor Faktur       : Auto-generate
Tanggal Faktur     : Pick date
Supplier           : Dropdown (dari master)
Payment Type       : TOP / CASH
Due Date (jika TOP): Auto-calculate

Per Item:
├─ Kategori Barang    (BAN/OLI/AKI/SPAREPART)
├─ Kode Barang
├─ Nama Barang
├─ Harga Faktur       (dari supplier)
├─ Harga Netto        (flexible)
└─ Jumlah (Qty)
```

---

## 3️⃣ INVENTORY MANAGEMENT (Data Barang - Owner Only)

```
┌──────────────────────────────────────────────────────┐
│   DATA BARANG MASTER (Owner Only)                    │
│   Multi-Supplier Support                             │
└──────────────────────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────┐
    │  PRODUCT MASTER (Per Product)              │
    │  ────────────────────────────────────────  │
    │  • Kode Barang (unique)                    │
    │  • Nama Barang                             │
    │  • Kategori                                │
    │  • Total Stock QTY (all suppliers)         │
    │  • Status (Active/Inactive)                │
    └────────────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────┐
    │  SUPPLIER PRICING (Multiple records)       │
    │  ────────────────────────────────────────  │
    │  FOR EACH SUPPLIER:                        │
    │  ├─ Supplier Name (link)                   │
    │  ├─ Harga Faktur (dari supplier ini)       │
    │  ├─ Harga Netto (flexible, per supplier)   │
    │  ├─ Last Purchase Date                     │
    │  └─ Supplier Status (Active/Inactive)      │
    │                                            │
    │  Example: Ban Bridgestone A/T              │
    │  ├─ Supplier A: Rp 100.000                 │
    │  ├─ Supplier B: Rp 95.000                  │
    │  └─ Supplier C: Rp 98.000                  │
    └────────────────────────────────────────────┘
         │
    ┌────┴────┬──────────────────┐
    ▼         ▼                  ▼
┌────────┐ ┌─────────┐    ┌──────────────────┐
│ Stock  │ │Selling  │    │ Price Tiers      │
│ Level  │ │ Prices  │    │ (Same for all    │
│        │ │(Global) │    │  suppliers)      │
├────────┤ │         │    ├──────────────────┤
│Current │ │ ECER    │    │ECER: Rp XXX      │
│Qty     │ │ BENGKEL │    │(1-5 pcs)         │
│        │ │ GROSIR  │    │                  │
│        │ │         │    │BENGKEL: Rp X     │
│        │ │         │    │(6-10 pcs)        │
│        │ │         │    │                  │
│        │ │         │    │GROSIR: Rp X      │
│        │ │         │    │(11-100 pcs)      │
└────────┘ └─────────┘    └──────────────────┘
```

**Key Features:**
- ✅ **Multi-supplier support** - Same product from multiple suppliers
- ✅ Track supplier-specific pricing (Harga Faktur per supplier)
- ✅ Flexible net price per supplier
- ✅ Multiple pricing tiers (ECER/BENGKEL/GROSIR) - same for all suppliers
- ✅ Real-time stock updates (combined from all sources)
- ✅ Supplier cost comparison for procurement optimization

---

## 4️⃣ SALES TRANSACTION PHASE (Owner & Karyawan)

```
┌────────────────────────────────────────┐
│   TRANSAKSI PENJUALAN (Sales Entry)    │
│   Access: Owner & Karyawan             │
└────────────────────────────────────────┘
         │
    ┌────┴─────────────────────────────┐
    ▼                                   ▼
┌────────────────────┐        ┌──────────────────┐
│ Select Customer    │        │ Create Invoice   │
│ - From master list │        │ Auto-gen Invoice │
│ - Or New (walk-in) │        │ Number/Date      │
└────────────────────┘        └──────────────────┘
    │                                   │
    └────┬────────────────────────────┬─┘
         │                            │
         ▼                            ▼
    ┌──────────────────────────────┐ ┌──────────────┐
    │  Add Product Items           │ │ Payment Info │
    │  FOR EACH ITEM:              │ │              │
    │  - Search/Scan Barcode       │ │ Method:      │
    │  - Select Product            │ │ TUNAI/XFER   │
    │  - Enter Quantity            │ │              │
    │                              │ │ Amount paid  │
    │  *** AUTO-DETECT PRICE ***   │ │ Calculate    │
    │  IF Qty = 1-5  → ECER        │ │ Kembalian    │
    │  IF Qty = 6-10 → BENGKEL     │ └──────────────┘
    │  IF Qty = 11+  → GROSIR      │
    │                              │
    │  - Show Applied Price        │
    │  - Calculate Subtotal        │
    │  - Add to invoice            │
    └──────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────┐
    │ Calculate Transaction Summary    │
    │ - Total Items: X                 │
    │ - Total Quantity: X pcs          │
    │ - Grand Total: Rp XXX.XXX        │
    │ - Amount Paid: Rp XXX            │
    │ - Kembalian: Rp XXX (change)     │
    └──────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────┐
    │ Output Invoice                   │
    │ ✅ Customer name                 │
    │ ✅ Invoice #                     │
    │ ✅ Date                          │
    │ ✅ Item Names ONLY (no supplier) │
    │ ✅ Qty × Unit Price per item     │
    │ ✅ Total Amount                  │
    │ ✅ Payment Method                │
    │ ✅ Change (if cash)              │
    └──────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────┐
    │ Update Stock (Auto-decrement)    │
    │ - Reduce qty per item            │
    └──────────────────────────────────┘
```

**Auto-Detection Logic:**
```
QTY Entered → Harga Jual Applied:
├─ 1-5 qty     → ECER price
├─ 6-10 qty    → BENGKEL price
└─ 11-100 qty  → GROSIR price
```

---

## 5️⃣ STOCK REPORT (Owner & Karyawan)

```
┌──────────────────────────────────┐
│  LAPORAN STOK (Stock Report)     │
│  Access: Owner & Karyawan        │
└──────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────┐
    │ View Current Stock           │
    │ Per Product:                 │
    │ - Kode Barang                │
    │ - Nama Barang                │
    │ - Kategori                   │
    │ - Jumlah Stok (PCS)          │
    │ - Harga Jual (3 tiers)       │
    │   └─ ECER/BENGKEL/GROSIR     │
    └──────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────┐
    │ Filter & Search              │
    │ - By kategori                │
    │ - By supplier                │
    │ - By stock level             │
    │ - Low stock alert            │
    └──────────────────────────────┘
```

---

## 6️⃣ COMPREHENSIVE REPORTING (Owner Only)

```
┌──────────────────────────────────────────┐
│      LAPORAN (Comprehensive Reports)     │
│      Access: Owner Only                  │
└──────────────────────────────────────────┘
         │
    ┌────┼────┬────────┬──────────┐
    ▼    ▼    ▼        ▼          ▼
┌──────────────────────────────────────┐
│ LAPORAN PEMBELIAN (Purchase Report)  │
│ Periode: From Date → To Date         │
│                                      │
│ Per Supplier:                        │
│ ├─ Nama Supplier                     │
│ ├─ Total Invoice(s)                  │
│ └─ Total Amount (Rp)                 │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│ LAPORAN PENJUALAN (Sales Report)     │
│ Periode: From Date → To Date         │
│                                      │
│ Per Customer:                        │
│ ├─ Nama Customer                     │
│ ├─ Invoice #                         │
│ ├─ Total Items                       │
│ └─ Total Amount (Rp)                 │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│ LAPORAN LABA (Profit Report)         │
│ Periode: From Date → To Date         │
│                                      │
│ Summary:                             │
│ ├─ Total Penjualan (Revenue)         │
│ ├─ Total Pembelian (Cost)            │
│ ├─ Laba/Rugi (Profit/Loss)           │
│ └─ Profit Margin (%)                 │
│                                      │
│ Per Transaction:                     │
│ ├─ Invoice #                         │
│ ├─ Profit per item                   │
│ └─ Profit %                          │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│ LAPORAN RETUR (Return Report)        │
│ Periode: From Date → To Date         │
│                                      │
│ Retur Pembelian (from supplier):     │
│ ├─ Supplier Name                     │
│ ├─ Return Date                       │
│ ├─ Original Invoice #                │
│ ├─ Kode Barang                       │
│ ├─ Nama Barang                       │
│ ├─ Qty returned                      │
│ └─ Reason                            │
│                                      │
│ Retur Penjualan (from customer):     │
│ ├─ Customer Name                     │
│ ├─ Return Date                       │
│ ├─ Original Invoice #                │
│ ├─ Kode Barang                       │
│ ├─ Nama Barang                       │
│ ├─ Qty returned                      │
│ └─ Reason                            │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│ LAPORAN STOK LARIS (Top Sellers)     │
│ Periode: From Date → To Date         │
│                                      │
│ Ranked by:                           │
│ ├─ Most Sold (qty)                   │
│ ├─ Most Profitable                   │
│ ├─ Revenue generated                 │
│ └─ Inventory turnover                │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│ LAPORAN GLOBAL (Summary Dashboard)   │
│ Periode: From Date → To Date         │
│                                      │
│ Key Metrics:                         │
│ ├─ Total Revenue                     │
│ ├─ Total Cost                        │
│ ├─ Net Profit                        │
│ ├─ Items Sold (qty)                  │
│ ├─ Customers Served                  │
│ ├─ Transactions Count                │
│ └─ Average Transaction Value         │
└──────────────────────────────────────┘
```

---

## 7️⃣ RETURN MANAGEMENT (Owner & Karyawan)

```
┌───────────────────────────────────────┐
│         RETUR MANAGEMENT              │
└───────────────────────────────────────┘
         │
    ┌────┴────────────────┐
    ▼                     ▼
┌──────────────────┐  ┌─────────────────┐
│RETUR PEMBELIAN   │  │RETUR PENJUALAN  │
│(from Supplier)   │  │(from Customer)  │
│Owner Access Only │  │Owner & Karyawan │
└──────────────────┘  └─────────────────┘
    │                     │
    ├─ Supplier Name      ├─ Customer Name
    ├─ Return Date        ├─ Return Date
    ├─ Original Invoice # ├─ Original Invoice #
    ├─ Product Code       ├─ Product Code
    ├─ Product Name       ├─ Product Name
    ├─ Return Qty         ├─ Return Qty
    └─ Reason             └─ Reason
         │                     │
         ▼                     ▼
    ┌──────────────────┐  ┌─────────────────┐
    │ Adjust Supplier  │  │Adjust Customer  │
    │ Credit/Balance   │  │Credit/Balance   │
    │ Update Inventory │  │Update Inventory │
    │ (Restock items)  │  │(Restock items)  │
    └──────────────────┘  └─────────────────┘
         │                     │
         └──────────┬──────────┘
                    ▼
            ┌──────────────────┐
            │ Log Return       │
            │ in RETUR Report  │
            └──────────────────┘
```

---

## 🔐 ACCESS CONTROL MATRIX

```
┌─────────────────────────────────────────────────────┐
│  Feature                    │  Owner  │  Karyawan   │
├─────────────────────────────┼─────────┼─────────────┤
│ Setup Master Data           │    ✅   │      ❌     │
│ Input Pembelian             │    ✅   │      ❌     │
│ Data Barang (view)          │    ✅   │      ❌     │
│ Transaksi Penjualan         │    ✅   │      ✅     │
│ Laporan Stok (view)         │    ✅   │      ✅     │
│ Laporan Pembelian           │    ✅   │      ❌     │
│ Laporan Penjualan           │    ✅   │      ✅     │
│ Laporan Laba                │    ✅   │      ❌     │
│ Laporan Global              │    ✅   │      ❌     │
│ Retur Pembelian             │    ✅   │      ❌     │
│ Retur Penjualan             │    ✅   │      ✅     │
└─────────────────────────────────────────────────────┘
```

---

## 🔄 OFFLINE SYNC STRATEGY

### Device: Android/Tablet
```
┌────────────────┐          ┌─────────────┐
│  Local DB      │◄────────►│  Cloud DB   │
│  (Offline)     │  Sync    │  (Online)   │
└────────────────┘          └─────────────┘
     │
     ├─ Store all transactions locally
     ├─ Queue changes for sync
     ├─ Auto-sync when online
     ├─ Conflict resolution
     └─ Data validation
```

---

## 📊 KEY FEATURES SUMMARY

| Feature | Requirement | Status |
|---------|-------------|--------|
| Multi-Supplier Support | Same product from multiple suppliers | ✅ |
| Supplier Cost Comparison | Price per supplier | ✅ |
| Offline-First System | Android/Tablet | ✅ |
| Multi-User Access | Owner + Karyawan | ✅ |
| Flexible Netto Pricing | Per supplier + per transaction | ✅ |
| Auto-Detection Purchase Type | Based on qty | ✅ ECER(1-5)/BENGKEL(6-10)/GROSIR(11-100) |
| Dynamic Pricing 3-tier | Per product (global) | ✅ |
| Comprehensive Reporting | Date range + metrics + per supplier | ✅ |
| Stock Tracking | Real-time (all suppliers combined) | ✅ |
| Profit per Transaction | Detailed | ✅ |
| Top-Selling Items Report | Ranked | ✅ |
| Invoice (Item names only) | No supplier names | ✅ |
| Returns Management | Both directions + per supplier | ✅ |

---

## 🗂️ DATA RELATIONSHIPS

```
SUPPLIER ──┐
           ├──► BARANG ──┐
KATEGORI ──┤             ├──► PEMBELIAN
           └─────────────┤
                         │
                    ┌────┴────┐
                    ▼         ▼
               PENJUALAN   STOCK
                    │         │
                    └────┬────┘
                         ▼
                     LAPORAN
                    (All types)
```

---

**Created:** 2026-08-03  
**Status:** Ready for Implementation  
**Next Step:** Create Excalidraw visual diagram
