# Student Excel Import Guide

## Overview
The School Management System now supports bulk import of student data from Excel (.xls, .xlsx, .xlsm) or CSV files.

## How to Use

### 1. Access Import Feature
- Go to **Students Management** page
- Click the **"Import from Excel"** button (green button with Excel icon)

### 2. Prepare Your File

#### Required Format:
- **First row**: Column headers
- **Required column**: `name` (student full name)
- **Optional columns**: `age`, `contact`, `class`

#### Sample CSV Format:
```csv
name,age,contact,class
John Doe,16,john@example.com,Class 10A
Jane Smith,15,555-0123,Class 9B
Bob Johnson,17,bob@email.com,Class 11C
```

#### Sample Excel Format:
| name       | age | contact         | class     |
|------------|-----|-----------------|-----------|
| John Doe   | 16  | john@example.com| Class 10A |
| Jane Smith | 15  | 555-0123       | Class 9B  |
| Bob Johnson| 17  | bob@email.com   | Class 11C |

### 3. Download Sample Template
- Click **"Download Sample CSV"** in the import modal
- Use this as a starting point for your data

### 4. Upload File
- Select your Excel/CSV file
- Click **"Import Students"**
- Maximum file size: 10MB

## Validation Rules

### Required Fields:
- **Name**: Must be provided and cannot be empty

### Optional Fields:
- **Age**: Must be numeric if provided
- **Contact**: Can be phone number or email
- **Class**: Must match existing class names exactly (case-insensitive)

### Data Integrity:
- Duplicate students (same name in same class) are rejected
- Empty rows are skipped
- Invalid data rows show specific error messages

## Supported File Types
- **Excel files**: .xls, .xlsx, .xlsm (requires PhpSpreadsheet library)
- **CSV files**: .csv (always supported)

## Error Handling
- Import uses database transactions - either all students import successfully or none do
- Detailed error messages show which rows failed and why
- Partial success: Shows how many students imported successfully plus any errors

## Tips
1. **Clean your data** before importing
2. **Use exact class names** as they appear in the system
3. **CSV is recommended** if you don't have Excel installed
4. **Check for duplicates** before importing
5. **Backup your data** before bulk operations

## Troubleshooting

### "Excel file support requires PhpSpreadsheet library"
- Use CSV format instead, or install PhpSpreadsheet via Composer

### "Class 'X' not found"
- Ensure class names match exactly (case-insensitive)
- Check existing classes in Classes Management

### "Name is required"
- Ensure the 'name' column exists and has data in all rows

### File too large
- Reduce file size or split into smaller files
- Maximum: 10MB