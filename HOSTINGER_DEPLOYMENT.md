# LabMaster - Hostinger Deployment Guide

This guide walks you through deploying **LabMaster** to your **Hostinger Web Hosting** using GitHub.

---

## Step 1: Create a MySQL Database on Hostinger
1. Log in to your **Hostinger hPanel** (`hpanel.hostinger.com`).
2. Go to **Databases** &rarr; **Management**.
3. Create a new MySQL database:
   - **Database Name**: e.g., `u123456789_labmaster`
   - **Username**: e.g., `u123456789_labuser`
   - **Password**: Create a strong password and save it securely.
4. Click **Enter phpMyAdmin** next to your newly created database.
5. In phpMyAdmin, go to the **Import** tab:
   - First import: `database/schema.sql` (Creates all 33 tables)
   - Second import: `database/seed_data.sql` (Inserts initial tests, settings, roles, and default users)

---

## Step 2: Deploy Code to Hostinger via GitHub

### Method A: Hostinger Git Feature (Automated CI/CD)
1. In Hostinger hPanel, search for **Git** in the left sidebar.
2. Under **Repository**:
   - **Repository URL**: `https://github.com/sagarstarters-tech/LabMaster.git`
   - **Branch**: `main`
   - **Install directory**: `public_html` (or a subdirectory like `public_html/lab` if deploying to a subfolder)
3. Click **Create** &rarr; **Deploy**.
4. Whenever you push code to GitHub in the future, click **Deploy** in Hostinger Git to update your live site!

### Method B: Manual File Manager Upload (Alternative)
- If you prefer direct upload, download the ZIP from GitHub and extract it into your `public_html` directory.

---

## Step 3: Configure Database Credentials on Hostinger
1. In Hostinger hPanel, open **File Manager**.
2. Navigate to `public_html/config/`.
3. Create a new file named **`database.local.php`** (or copy from `database.local.example.php`).
4. Enter your Hostinger database details:
   ```php
   <?php
   return [
       'host'    => 'localhost',
       'port'    => '3306',
       'name'    => 'u123456789_labmaster',    // Your Hostinger DB Name
       'user'    => 'u123456789_labuser',      // Your Hostinger DB User
       'pass'    => 'YourHostingerPassword',   // Your Hostinger DB Password
       'charset' => 'utf8mb4'
   ];
   ```
5. Save the file.
*(Note: `database.local.php` is ignored by Git, ensuring your live server credentials are never exposed publicly!)*

---

## Step 4: Storage Directory Permissions
In Hostinger File Manager, ensure the following directory has write permissions (`0775` or `0755`):
- `storage/logs/`
- `storage/reports/`
- `assets/signatures/` (for pathologist digital signatures)

---

## Default Login Credentials

| Role | Username | Password |
| :--- | :--- | :--- |
| **Super Administrator** | `admin` | `Admin@123` |
| **Pathologist** | `dr.sharma` | `Patho@123` |
| **Laboratory Technician** | `tech.anil` | `Tech@123` |
| **Receptionist** | `reception` | `Staff@123` |

*(Be sure to change default passwords after logging in via **My Profile**).*
