# Guidance Office Logbook Management System (OSAS) - Part 1

A polished, responsive logbook management system for the school Guidance & Counseling Center under the Office of Student Affairs and Services (OSAS). Built directly from the official paper logbook templates (`Logbook.docx`), supporting desktop and mobile devices, role-based access, full audit trail, paper-style printing, data export, and dynamic runtime creation of new logbook sections.

---

## 🌟 Key Features

### 1. The 4 Core Logbook Sections (from `Logbook.docx`)
1. **Borrower’s Log Sheet**
   - Fields: `No.`, `Date`, `Name`, `Client Type` (*Student*, *Visitor*, *Alumni*, *Faculty*), `Course & Year / Department`, `Purpose / Item Borrowed`, `Date of Return`, `Return Status` (*Pending*, *Returned*, *Overdue*), and `Remarks`.
   - Automatic calculation of unreturned borrowed items with dashboard alert badges.
2. **Certificate of Good Moral Character**
   - Fields: `No.`, `Date Requested`, `Year Graduated / Level`, `Name`, `Purpose` (*PRC/Government Exam*, *Scholarship*, *Transfer*, *Employment*, *Other*), `Purpose Details`, `Signature / Confirmation`, `Date Issued`, and `Remarks / OR No.`.
   - Real-time tracking of pending vs. released certificates.
3. **Client’s Log Sheet**
   - Fields: `No.`, `Date`, `Name`, `Client Type` (*Student*, *Visitor*, *Alumni*, *Faculty*), `Course & Year / Office`, `Purpose` (*Routine Counseling*, *Walk-in Consultation*, *Clearance Signing*, *Certificate Request*, *Exit Interview*, *Inquiry*, *Other*), `Time-in`, `Time-out`, and `Signature / Confirmation`.
4. **Visitor’s Log Sheet**
   - Fields: `No.`, `Date`, `Name`, `Agency / Office`, `Purpose of Visit`, `Time-in`, `Time-out`, `Signature / Confirmation`, and `Remarks / Contact No.`.

---

### 2. Interactive First-Screen Dashboard (`index.php`)
- **Date Range Filters**: *All Time*, *Today*, *This Week*, *This Month*, *This Year*, or Custom date range (`From` – `To`).
- **Interactive Metric Cards (Click to Filter)**:
  - **Total Client Visits**: Opens Client's Log pre-filtered for the selected period.
  - **Total Visitors**: Opens Visitor's Log.
  - **Borrowed Items Not Yet Returned**: Opens Borrower's Log filtered directly to *Pending* return status!
  - **Certificates Issued**: Opens Good Moral registry.
- **Charts & Visual Trends (Chart.js)**:
  - Daily & period activity timeline (traffic volume).
  - Common visit purpose distribution doughnut chart (*Counseling*, *Consultation*, *Clearance*, *Certificates*, etc.).
  - Client demographics breakdown (*Student*, *Visitor*, *Alumni*, *Faculty*).
- **Attention Center**: Quick-action table for overdue borrowed equipment and items pending return.
- **Recent Entries Feed**: Unified real-time activity stream across all logbooks.

---

### 3. Usability & Record Management
- **Auto-Numbering with Admin Override**:
  - Automatically assigns the next sequential record number per logbook.
  - Administrators can check *Admin Override* to correct or customize record numbers with audit tracking.
- **Search & Multi-Facet Filters**:
  - Live search across name, course, agency, purpose, and remarks.
  - Date-range filters.
  - Specific filters for Client Type, Purpose category, and Return Status (*Returned*, *Pending*, *Overdue*).
- **Paper Logbook Print Replica (`print.php`)**:
  - Renders a clean print-ready layout matching the exact column layout and sub-headers from `Logbook.docx` (e.g. `Client` with sub-columns `Student | Visitor | Alumni | Faculty` and checkmarks `✓`).
  - Includes official header: Republic of the Philippines, School Name, OSAS, Guidance and Counseling Center.
  - Formatted for standard A4 / Letter landscape print or PDF generation.
  - Official signatories block: *Prepared by Guidance Staff*, *Attested by Guidance Counselor / OSAS Director*.
- **Data Export (`export.php`)**:
  - **CSV Export**: Clean UTF-8 with BOM for direct compatibility with Microsoft Excel.
  - **Excel (.xls) Export**: Formatted spreadsheet with bold branded headers and proper gridlines.
- **Safe Archiving & Deletion**:
  - Prevents accidental loss by providing soft-archiving with instant 1-click restore.
  - Permanent deletion is restricted to Administrators and requires explicit double confirmation.

---

### 4. Dynamic Section Builder (`manage_types.php`) - Admin Only
Add new logbook sections (e.g., *Counseling Intake Log*, *Referral Log*, *Exit Interview Log*, *Inventory Log*) without writing code:
- Configure Title, Slug, Description, Icon, and Color Theme.
- Add dynamic fields:
  - `Text`, `Number`, `Date`, `Time`
  - `Select Dropdown` (with comma-separated options)
  - `Signature / Checkbox`
  - `Textarea / Notes`
- Toggle `Required`, `Show in Table`, and `Show in Filter`.
- The new section instantly appears in the navigation bar, with its own table, auto-numbering, CSV/Excel export, and paper sheet print view!

---

### 5. Privacy, Role-Based Access & Auditability
- **Role-Based Sign-In**:
  - `Administrator`: Full access, section builder, user management, backup/restore, hard deletes.
  - `Staff`: Daily logging, viewing records, editing, archiving, printing, and exporting.
- **Audit Trail (`audit_logs.php`)**:
  - Every creation, edit, archive, restore, and delete logs the timestamp, user ID, user full name, IP address, and details.
- **Database Backup & Disaster Recovery (`backup.php`)**:
  - 1-click full SQL export download.
  - Safe restore from uploaded `.sql` backup file.

---

## 🚀 How to Run the System in XAMPP

1. **Verify XAMPP Services**:
   - Open the **XAMPP Control Panel**.
   - Ensure **Apache** and **MySQL** are running (green status).

2. **Access via Browser**:
   - Open your web browser and navigate to:
     ```
     http://localhost/LOG BOOK OSAS/
     ```
     *(or `http://localhost/LOG%20BOOK%20OSAS/login.php`)*

## 👥 User Roles (Administrator & Student)

There are **two distinct user flows** in the system:
1. **Student / Client (Walk-in Data Collection Portal)**:
   - URL: `http://localhost/LOG%20BOOK%20OSAS/fill.php` *(or `kiosk.php`)*
   - **No login needed**. Designed for touchscreens, tablets, kiosk computers at the entrance, or mobile phone QR codes.
   - Students, visitors, alumni, and faculty choose their logbook section, enter their details, confirm their digital signature, and receive an instant reference number ticket.
2. **Administrator (Office Management)**:
   - URL: `http://localhost/LOG%20BOOK%20OSAS/login.php`
   - **Username**: `admin`
   - **Password**: `admin123`
   - Full oversight: monitors live student log submissions, updates return dates, marks certificates issued, filters/searches, exports CSV/Excel, prints paper log sheets, and builds new custom logbook sections.

---

## 🛠️ How to Add Another Logbook Type

1. Sign in with the **Administrator** account (`admin`).
2. Click **Logbook Section Builder** in the left sidebar (or navigate to `manage_types.php`).
3. Scroll to the **Create Additional Logbook Section** form.
4. Fill in the section details:
   - **Section Title**: e.g., `Referral & Case Consultation Log`
   - **URL Slug**: e.g., `referral-case-log`
   - **Icon**: Choose an icon (e.g., `bi-folder-symlink` or `bi-journal-check`)
   - **Description**: e.g., `Log of students referred by faculty, deans, or parents for behavioral or academic follow-up.`
5. Click **+ Add Field** to add custom fields:
   - *Example Field 1*: Label: `Referred By`, Type: `Text`, Required: Yes.
   - *Example Field 2*: Label: `Referral Reason`, Type: `Select Dropdown`, Options: `Academic Difficulty, Chronic Absences, Behavioral Concern, Emotional Distress, Financial/Family Concern`, Required: Yes.
   - *Example Field 3*: Label: `Assigned Counselor`, Type: `Text`, Required: Yes.
   - *Example Field 4*: Label: `Case Notes`, Type: `Textarea / Notes`, Required: No.
6. Click **Create & Activate Section**.
7. The new logbook will immediately appear in the sidebar, ready for recording, filtering, printing, and CSV/Excel exports!
