# Petrol Pump ERP - API Updates (Today)

This document exclusively lists the API endpoints that were modified today to support the new features: per-nozzle testing quantities, creditor ledger integration from daily sales, and date-filtered dashboard inventory stats.

---

## 1. Daily Sales API

### Export Sales Report (CSV)
Export a daily sales report in CSV format (Excel compatible) matching the grid view format.
*   **URL**: `/api/daily-sales/export`
*   **Method**: `GET`
*   **Query Parameters**:
    *   `start_date` (optional): Filter from this date (`dd/mm/yyyy` or `yyyy-mm-dd`).
    *   `end_date` (optional): Filter to this date (`dd/mm/yyyy` or `yyyy-mm-dd`).
*   **Response**: Downloads a `sales_report.csv` file.

### Store / Update Daily Sale
Record a new day's readings, rates, and cash reconciliation metrics.
*   **URL**: `/api/daily-sales` (or `PUT /api/daily-sales/{id}` for updates)
*   **Method**: `POST` | `PUT`
*   **Updated Payload Parameters**:
    | Parameter | Type | Description |
    | :--- | :--- | :--- |
    | `credit_payments` | `array` | **[NEW]** Array of credit payments received (requires `creditor_id` and `amount`). |
    | `credit_givens` | `array` | **[NEW]** Array of credit given (requires `creditor_id` and `amount`). |
    | `online_payments` | `array` | **[NEW]** Array of online payments (requires `name` and `amount`). |
    | `staff_payouts` | `array` | **[NEW]** Array of staff payouts (requires `name` and `amount`). |
    | `readings.*.testing_qty` | `numeric` | Optional custom testing quantity per nozzle. |

*   **JSON Payload Example**:
    ```json
    {
      "date": "2026-05-21",
      "rate_ms": 98.17,
      "rate_hsd": 92.93,
      "rate_xp": 107.05,
      "lubricant_sale": 0.00,
      "density": 0.00,
      
      "credit_payments": [
        { "creditor_id": 3, "amount": 1000.00 }
      ],
      "credit_givens": [
        { "creditor_id": 2, "amount": 540.00 }
      ],
      "online_payments": [
        { "name": "PhonePe User", "amount": 52167.00 },
        { "name": "Paytm QR", "amount": 1000.00 }
      ],
      "staff_payouts": [
        { "name": "Rajiv", "amount": 2000.00 }
      ],
      
      "ac_transfer": 0.00,
      "expense": 0.00,
      "owner_1": 0.00,
      "owner_2": 0.00,
      "cash_collection": 178000.00,
      "coin": 0.00,
      "miss": 0.00,
      "balance_in_pump": 32555.00,

      "readings": [
        { "nozzle_id": 1, "closing_reading": 324333.38, "testing_qty": 5.00 },
        { "nozzle_id": 2, "closing_reading": 1285132.04, "testing_qty": 15.00 }
      ]
    }
    ```

---

## 2. Dashboard API

### Get Dashboard Stats
Retrieve real-time pump tank fuel volumes and inventory remaining. The fuel remaining calculation now strictly evaluates `Opening Fuel + Purchases - Daily Sales`.

*   **URL**: `/api/dashboard/stats`
*   **Method**: `GET`
*   **Updated Query Parameters**:
    *   `date` (optional): **[NEW]** Filter fuel inventory calculations up to this specific date (`dd/mm/yyyy` or `yyyy-mm-dd`). Defaults to the current date if omitted.

*   **Response (200 OK)**:
    ```json
    {
      "success": true,
      "stats": {
        "fuel_remaining": {
          "MS": 45813.86,
          "HSD": 27687.52,
          "XP": 1123.45,
          "total": 74624.83
        }
      }
    }
    ```

---

## 3. Cashbook API (Receipts & Payments)

### List Cashbook Records
*   **URL**: `/api/cashbooks`
*   **Method**: `GET`
*   **Query Parameters**:
    *   `start_date`, `end_date`, `date`: Date filters (`YYYY-MM-DD`).
    *   `type`: Filter by `receipt` or `payment`.

### Store / Update Cashbook Record
*   **URL**: `/api/cashbooks` (or `PUT /api/cashbooks/{id}`)
*   **Method**: `POST` | `PUT`
*   **Payload Parameters**:
    | Parameter | Type | Description |
    | :--- | :--- | :--- |
    | `date` | `date` | The date of the transaction. |
    | `type` | `enum` | Must be either `receipt` or `payment`. |
    | `particulars` | `string` | The description/name (e.g. "PHONE PAY"). |
    | `cash_amount` | `numeric` | Amount received/paid in cash. |
    | `bank_amount` | `numeric` | Amount received/paid in bank. |
    | `remark` | `string` | Optional note. |

### Export Cashbook Report (CSV)
Export a side-by-side (Receipts on left, Payments on right) CSV file matching the exact Cashbook layout.
*   **URL**: `/api/cashbooks/export`
*   **Method**: `GET`
*   **Query Parameters**: `start_date`, `end_date` (optional).
*   **Response**: Downloads `cashbook_report.csv`.
