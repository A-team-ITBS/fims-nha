# Project Setup Guide - __BASE__ Template

This guide explains how to duplicate and customize the `__BASE__` template to create new PHPMaker projects with organized architecture and consistent structure.

## 📋 Table of Contents
1. [Template Overview](#template-overview)
2. [Duplication Process](#duplication-process)
3. [Customization Steps](#customization-steps)
4. [Architecture Structure](#architecture-structure)
5. [Development Guidelines](#development-guidelines)
6. [Best Practices](#best-practices)

## 🎯 Template Overview

The `__BASE__` template provides a standardized starting point for PHPMaker projects with:

- ✅ Organized API architecture (routes vs services separation)
- ✅ JWT authentication middleware
- ✅ Permission-based access control
- ✅ Notification system integration
- ✅ Consistent namespace and naming conventions
- ✅ Template placeholders for easy customization

### Key Template Placeholders

| Placeholder | Description | Examples |
|-------------|-------------|----------|
| `__BASE__` | Project/System code | UAC, DMS, LMS, IMS |
| `ITBS_STANDARD_APP` | PHP Namespace | UAC, DocumentManagement, LibrarySystem |

## 🔄 Duplication Process

### Step 1: Copy Template Directory

```bash
# Navigate to your projects directory
cd /d/Projects/YOUR_PROJECT_AREA/

# Copy the entire __BASE__ directory
cp -r __BASE__ YOUR_PROJECT_CODE
```

**Example:**
```bash
cp -r __BASE__ UAC
cp -r __BASE__ DMS  
cp -r __BASE__ LMS
```

### Step 2: Rename Project Files

Rename files that contain `__BASE__` in their names:

```bash
# Navigate to your new project
cd YOUR_PROJECT_CODE/_source

# Rename .pmp file
mv _BASE_.pmp YOUR_PROJECT_CODE.pmp
```

**Example:**
```bash
cd UAC/_source
mv _BASE_.pmp UAC.pmp
```

## ⚙️ Customization Steps

### Step 3: Replace Namespace Placeholders

Replace `ITBS_STANDARD_APP` with your project namespace in all files:

#### Files to Update:
- `/app/api.php`
- `/app/routes/notifications.php`
- `/app/routes/service.php`
- `/app/global.php`
- Any custom pages in `/app/pages/`

#### Find & Replace:
```
Find: ITBS_STANDARD_APP
Replace: YOUR_NAMESPACE
```

**Examples:**
- `UAC` - For User Access Control
- `DocumentManagement` - For Document Management System
- `LibrarySystem` - For Library Management System
- `InventoryManagement` - For Inventory Management System

### Step 4: Replace System Code Placeholders

Replace `__BASE__` with your project code:

#### Find & Replace:
```
Find: __BASE__
Replace: YOUR_PROJECT_CODE
```

**Common Locations:**
- System identifiers in notification routes
- Documentation references
- Directory names in comments
- Database system codes

### Step 5: Update PHPMaker Project

1. Open PHPMaker
2. Open the renamed `.pmp` file
3. Update project settings:
   - **Project Name**: Your project name
   - **Namespace**: Match your replaced namespace
   - **Database Connection**: Configure your database
4. Regenerate the project

## 🏗️ Architecture Structure

After customization, your project will have this organized structure:

```
YOUR_PROJECT_CODE/
├── _source/
│   ├── YOUR_PROJECT_CODE.pmp     # PHPMaker project file
│   ├── PROJECT_SETUP.md          # This setup guide
│   └── sql/                      # Database scripts
│       ├── 01_init.sql
│       ├── 02_data.sql
│       └── ...
├── app/
│   ├── api.php                   # Main API orchestrator
│   ├── global.php                # Global configurations
│   ├── services/                 # Business logic classes
│   │   ├── YourService.php       # Custom service classes
│   │   └── README.md
│   ├── routes/                   # API route definitions
│   │   ├── notifications.php     # Notification routes
│   │   ├── service.php          # General service routes
│   │   ├── your-feature.php     # Custom feature routes
│   │   └── README.md
│   ├── pages/                    # Custom UI pages
│   │   ├── dashboard.php
│   │   └── custom-pages.php
│   └── lib/                      # Utility libraries
└── [PHPMaker Generated Files]
```

## 🛠️ Development Guidelines

### Creating New Features

#### 1. **Simple CRUD Operations**
Use PHPMaker's built-in forms and pages:
```php
// Let PHPMaker handle standard operations
<button data-ew-action="modal" data-url="UsersAdd">Add User</button>
```

#### 2. **Complex Business Logic**
Create custom service classes in `/app/services/`:

```php
<?php
namespace PHPMaker2024\YOUR_NAMESPACE;

class FeatureService
{
    public function complexOperation($data)
    {
        try {
            Execute("BEGIN", "DB");
            
            // Complex multi-table operations
            $sql = "INSERT INTO table (field) VALUES (" . 
                   QuotedValue($data['field'], DataType::STRING) . ")";
            Execute($sql, "DB");
            
            Execute("COMMIT", "DB");
            return json_encode(['success' => true]);
        } catch (\Exception $e) {
            Execute("ROLLBACK", "DB");
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
```

#### 3. **API Endpoints**
Create route files in `/app/routes/`:

```php
<?php
namespace PHPMaker2024\YOUR_NAMESPACE;

$app->post("/your-feature/action", function ($request, $response) {
    $service = new FeatureService();
    $data = $request->getParsedBody();
    $result = $service->complexOperation($data);
    return $response->write($result);
})->add($accessMiddleware)->add($jwtMiddleware);
```

#### 4. **Include New Routes**
Add to `/app/api.php`:
```php
// Include Route Files
include(dirname(__DIR__, 1) . "/app/routes/your-feature.php");
```

### Database Operations

Always use PHPMaker's secure query functions:

```php
// ✅ Correct - Use QuotedValue() for security
$sql = "SELECT * FROM users WHERE user_id = " . QuotedValue($userId, DataType::NUMBER);
$result = ExecuteRows($sql, "DB");

// ✅ String values
$sql = "INSERT INTO logs (message) VALUES (" . QuotedValue($message, DataType::STRING) . ")";
Execute($sql, "DB");

// ❌ Avoid - SQL injection risk
$sql = "SELECT * FROM users WHERE user_id = " . $userId;
```

## 📋 Best Practices

### 1. **Project Naming Conventions**

| Type | Convention | Examples |
|------|------------|----------|
| Project Code | UPPERCASE, 3-4 chars | UAC, DMS, LMS, IMS |
| Namespace | PascalCase | UAC, DocumentManagement, LibrarySystem |
| Route Files | kebab-case.php | user-management.php, document-processing.php |
| Service Classes | PascalCase + Service | UserService, DocumentService, NotificationService |

### 2. **File Organization**

```
✅ Good Structure:
/app/services/UserManagementService.php
/app/routes/user-management.php
/app/pages/user-dashboard.php

❌ Avoid:
/app/user_stuff.php
/app/misc/random_functions.php
```

### 3. **API Design**

```php
// ✅ RESTful endpoints
GET    /users              # List users
POST   /users              # Create user
GET    /users/{id}         # Get specific user
PUT    /users/{id}         # Update user
DELETE /users/{id}         # Delete user

// ✅ Grouped feature endpoints
GET    /notifications/recent
POST   /notifications/send
GET    /reports/generate
```

### 4. **Error Handling**

```php
// ✅ Consistent error responses
return $response->withJson([
    'success' => false,
    'message' => 'User-friendly error message',
    'code' => 'VALIDATION_ERROR'
]);

// ✅ Success responses
return $response->withJson([
    'success' => true,
    'data' => $result,
    'message' => 'Operation completed successfully'
]);
```

### 5. **Middleware Usage**

```php
// ✅ Authentication required
$app->get("/protected-route", $handler)->add($jwtMiddleware);

// ✅ Authentication + Permission check
$app->post("/admin-action", $handler)
    ->add($accessMiddleware)
    ->add($jwtMiddleware);

// ✅ Public endpoint (no middleware)
$app->get("/public-info", $handler);
```

## 🔧 Common Customizations

### System-Specific Replacements

When setting up different project types, use these conventions:

#### User Access Control (UAC)
```
Project Code: UAC
Namespace: UAC
System Name: User Access Control
Database Prefix: uac_
```

#### Document Management (DMS)
```
Project Code: DMS
Namespace: DocumentManagement  
System Name: Document Management System
Database Prefix: dms_
```

#### Library Management (LMS)
```
Project Code: LMS
Namespace: LibraryManagement
System Name: Library Management System
Database Prefix: lms_
```

#### Inventory Management (IMS)
```
Project Code: IMS
Namespace: InventoryManagement
System Name: Inventory Management System
Database Prefix: ims_
```

### Database Setup

1. Create your project database
2. Update connection settings in PHPMaker
3. Run initialization scripts from `/_source/sql/`
4. Regenerate the project

### Testing Your Setup

1. **Verify API Endpoints**:
   ```bash
   # Test basic endpoint
   curl -X GET "http://localhost/YOUR_PROJECT/api/Service"
   
   # Test with authentication
   curl -X GET "http://localhost/YOUR_PROJECT/api/notifications/count" \
        -H "Authorization: Bearer YOUR_JWT_TOKEN"
   ```

2. **Check Namespace**:
   - Verify no `ITBS_STANDARD_APP` references remain
   - Ensure all files use your new namespace

3. **Test PHPMaker Integration**:
   - Generate project in PHPMaker
   - Verify forms and pages work correctly
   - Test custom pages and API integration

## 🚀 Quick Start Checklist

- [ ] Copy `__BASE__` directory to new project folder
- [ ] Rename `_BASE_.pmp` to `YOUR_PROJECT.pmp`
- [ ] Replace `ITBS_STANDARD_APP` with your namespace
- [ ] Replace `__BASE__` with your project code
- [ ] Update PHPMaker project settings
- [ ] Configure database connection
- [ ] Run database initialization scripts
- [ ] Regenerate PHPMaker project
- [ ] Test API endpoints
- [ ] Verify custom pages functionality
- [ ] Update project documentation

## 📞 Support

For issues or questions about this template:
1. Check existing project examples (UAC, DMS, etc.)
2. Review PHPMaker documentation
3. Follow the troubleshooting guide in project documentation

---

**Happy coding! 🎉**

*This template accelerates development while maintaining consistency and best practices across all projects.*
