# PHPMaker Project Wizard

A beautiful Node.js wizard for duplicating PHPMaker `__BASE__` templates into new projects with automatic placeholder replacement.

## 🚀 Features

- **Beautiful ASCII Art Welcome Screen** - Professional-looking interface with colors and styling
- **Interactive Prompts** - User-friendly input validation and confirmation
- **Cross-Platform Support** - Works on Windows, macOS, and Linux
- **Automatic File Processing** - Recursively processes all supported file types
- **Smart Placeholder Replacement** - Replaces all template placeholders automatically
- **Progress Indicators** - Visual feedback during operations
- **Error Handling** - Comprehensive error handling and validation
- **File Explorer Integration** - Option to open the created project directory

## 📋 Prerequisites

- Node.js 14.0.0 or higher
- npm or yarn package manager

## 🛠️ Installation

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Make sure you have the `__BASE__` template directory** in the same location as this script.

## 🎯 Usage

### Method 1: Using npm script
```bash
npm start
# or
npm run wizard
```

### Method 2: Direct execution
```bash
node create_project_wizard.js
```

### Method 3: Make executable (Unix/Linux/macOS)
```bash
chmod +x create_project_wizard.js
./create_project_wizard.js
```

## 📝 What the Wizard Does

### 1. **Project Configuration**
- Prompts for **PROJECT_CODE** (3-4 uppercase letters, e.g., UAC, GSD, FMD)
- Prompts for **PHP Namespace** (PascalCase, e.g., UAC, GSD)
- Prompts for **Database Name** (defaults to `standard_db_[PROJECT_CODE]`)

### 2. **File Operations**
- Copies the entire `__BASE__` directory to the new project directory
- Renames `_BASE_.pmp` to `[PROJECT_CODE].pmp`
- Recursively processes all supported file types (`.php`, `.md`, `.pmp`, `.sql`, `.js`, `.json`)

### 3. **Placeholder Replacement**
- `__BASE__` → `PROJECT_CODE`
- `ITBS_STANDARD_APP` → `NAMESPACE`
- `standard_db_uac` → `DATABASE_NAME`

### 4. **Configuration Updates**
- Updates system configuration in `global.php`
- Updates documentation in `PROJECT_SETUP.md`
- Ensures all references are properly replaced

## 🎨 Example Usage

```
┌─────────────────────────────────────────────────────────────┐
│                    🚀 PHPMaker Project Duplication Wizard 🚀 │
│                                                             │
│              Transform __BASE__ template into your custom project │
└─────────────────────────────────────────────────────────────┘

✅ __BASE__ template found!

? Enter your PROJECT_CODE (3-4 uppercase letters): UAC
? Enter your PHP Namespace (PascalCase): UAC
? Enter your database name: standard_db_uac

┌─────────────────────────────────────────────────────────────┐
│                    📋 CONFIGURATION SUMMARY                │
└─────────────────────────────────────────────────────────────┘

Project Code:     UAC
Namespace:        UAC
Database Name:    standard_db_uac
Target Directory: UAC/

? Do you want to proceed? Yes

✅ Directory copied successfully!
✅ File renamed successfully!
✅ Processed 15 files successfully!
✅ System configuration updated!
✅ Documentation updated!

┌─────────────────────────────────────────────────────────────┐
│                    🎉 PROJECT CREATION COMPLETE!           │
└─────────────────────────────────────────────────────────────┘

✅ Successfully created project: UAC

📁 Project Location: /path/to/UAC
📄 PHPMaker File:   /path/to/UAC/_source/UAC.pmp
📚 Documentation:   /path/to/UAC/_source/PROJECT_SETUP.md

┌─────────────────────────────────────────────────────────────┐
│                    📋 NEXT STEPS                           │
└─────────────────────────────────────────────────────────────┘

1. Open PHPMaker and load: UAC/_source/UAC.pmp
2. Update project settings in PHPMaker
3. Configure database connection: standard_db_uac
4. Regenerate the project
5. Run `composer install` inside the new module directory
6. Wire the module into `nginx.conf` and `docker-compose.yml` (see "Registering a New Module" below — the wizard does not do this)
7. Add the module's tables to `_assets/sql/` and run `npm run install-system` (see the root [README.md](README.md#sql-directory) — the wizard does not create any schema for you, and `_source/sql/` is not a real per-module migration location in this codebase despite appearing in older docs)
8. Test your new project!

🎉 Happy coding with your new UAC project!

? Open project directory in file explorer? Yes
```

## 🔧 Dependencies

- **chalk** - Terminal string styling
- **inquirer** - Interactive command line prompts
- **fs-extra** - Enhanced file system operations
- **ora** - Elegant terminal spinners
- **boxen** - Create boxes in the terminal
- **figlet** - ASCII art text

## 📁 Project Structure

After running the wizard, your new project will have this structure:

```
YOUR_PROJECT_CODE/
├── _source/
│   ├── YOUR_PROJECT_CODE.pmp     # PHPMaker project file
│   ├── PROJECT_SETUP.md          # Setup guide
│   └── sql/                      # Database scripts
├── app/
│   ├── api.php                   # Main API orchestrator
│   ├── global.php                # Global configurations
│   ├── services/                 # Business logic classes
│   ├── routes/                   # API route definitions
│   ├── pages/                    # Custom UI pages
│   └── lib/                      # Utility libraries
└── [PHPMaker Generated Files]
```

## 🚨 Error Handling

The wizard includes comprehensive error handling:

- **Directory Validation** - Checks if `__BASE__` exists
- **Input Validation** - Validates project code, namespace, and database name formats
- **File Operations** - Handles copy, rename, and text replacement errors
- **Cross-Platform Support** - Works on Windows, macOS, and Linux

## 🎯 Next Steps After Creation

1. **Open PHPMaker** and load the new `.pmp` file
2. **Update project settings** in PHPMaker
3. **Configure database connection** with your database name
4. **Regenerate the project** in PHPMaker
5. **Run `composer install`** inside the new module directory
6. **Wire the module into `nginx.conf` and `docker-compose.yml`** — see "Registering a New Module" below
7. **Add the module's tables under `_assets/sql/`** and run `npm run install-system` from the repo root — see the root [README.md](README.md#sql-directory) for the phase-folder layout. The wizard does not create any database schema for you, and `_source/sql/` is **not** a real per-module migration location in this codebase (only a leftover exists in `UAC/_source/sql/`) — don't be misled by it.
8. **Test your new project** to ensure everything works correctly

## 🔌 Registering a New Module (manual steps)

The wizard only copies and renames files — it does **not** touch `nginx.conf`, `docker-compose.yml`,
or any module registry. After creating a module, wire it in by hand:

**1. `nginx.conf`** — add a location block pair for the new module (follow the existing pattern,
e.g. for `ACCOUNTING-DCD`):
```nginx
location /YOUR_PROJECT_CODE/ {
    try_files $uri $uri/ /YOUR_PROJECT_CODE/index.php$is_args$args;
}
location /YOUR_PROJECT_CODE/api/ {
    try_files $uri /YOUR_PROJECT_CODE/api/index.php$is_args$args;
}
```

**2. `docker-compose.yml`** — add a named volume for the module's `vendor/` directory (follow the
`fims-vendor-dcd`/`fims-vendor-gsd` pattern) and add the module's folder name to the
`composer install` loop (`for dir in UAC FMD TREASURY ... GSD; do`) so Docker builds install its
dependencies.

If the module has no `composer.json` (as with the `LIBIS` integration bridge), skip both of these.

## 🤝 Contributing

Feel free to submit issues and enhancement requests!

## 📄 License

MIT License - see LICENSE file for details.

---

**Happy coding! 🎉**