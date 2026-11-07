<?php
/**
 * PHPMaker 2024 User Level Settings
 */
namespace PHPMaker2024\FIMS;

/**
 * User levels
 *
 * @var array<int, string>
 * [0] int User level ID
 * [1] string User level name
 */
$USER_LEVELS = [["-2","Anonymous"],
    ["0","Default"]];

/**
 * User level permissions
 *
 * @var array<string, int, int>
 * [0] string Project ID + Table name
 * [1] int User level ID
 * [2] int Permissions
 */
$USER_LEVEL_PRIVS = [["{FIMS}audit_logs","-2","0"],
    ["{FIMS}audit_logs","0","0"],
    ["{FIMS}systems","-2","0"],
    ["{FIMS}systems","0","0"],
    ["{FIMS}user_level_assignments","-2","0"],
    ["{FIMS}user_level_assignments","0","0"],
    ["{FIMS}user_level_permissions","-2","0"],
    ["{FIMS}user_level_permissions","0","0"],
    ["{FIMS}user_levels","-2","0"],
    ["{FIMS}user_levels","0","0"],
    ["{FIMS}users","-2","0"],
    ["{FIMS}users","0","0"],
    ["{FIMS}aggregated_audit_logs","-2","0"],
    ["{FIMS}aggregated_audit_logs","0","0"],
    ["{FIMS}MainDashboard.php","-2","0"],
    ["{FIMS}MainDashboard.php","0","0"],
    ["{FIMS}departments","-2","0"],
    ["{FIMS}departments","0","0"],
    ["{FIMS}notifications","-2","0"],
    ["{FIMS}notifications","0","0"],
    ["{FIMS}UserManagement.php","-2","0"],
    ["{FIMS}UserManagement.php","0","0"],
    ["{FIMS}UserAccess.php","-2","0"],
    ["{FIMS}UserAccess.php","0","0"],
    ["{FIMS}psgc","-2","0"],
    ["{FIMS}psgc","0","0"],
    ["{FIMS}PSGCMap.php","-2","0"],
    ["{FIMS}PSGCMap.php","0","0"],
    ["{FIMS}FileManager.php","-2","0"],
    ["{FIMS}FileManager.php","0","0"],
    ["{FIMS}BullMQManager.php","-2","0"],
    ["{FIMS}BullMQManager.php","0","0"],
    ["{FIMS}PageEditor.php","-2","0"],
    ["{FIMS}PageEditor.php","0","0"],
    ["{FIMS}file_share_activity","-2","0"],
    ["{FIMS}file_share_activity","0","0"],
    ["{FIMS}file_share_permissions","-2","0"],
    ["{FIMS}file_share_permissions","0","0"],
    ["{FIMS}file_shares","-2","0"],
    ["{FIMS}file_shares","0","0"],
    ["{FIMS}shared_folder_access","-2","0"],
    ["{FIMS}shared_folder_access","0","0"],
    ["{FIMS}AiAssistant.php","-2","0"],
    ["{FIMS}AiAssistant.php","0","0"],
    ["{FIMS}chat_history","-2","0"],
    ["{FIMS}chat_history","0","0"],
    ["{FIMS}chat_sessions","-2","0"],
    ["{FIMS}chat_sessions","0","0"],
    ["{FIMS}position_categories","-2","0"],
    ["{FIMS}position_categories","0","0"],
    ["{FIMS}positions","-2","0"],
    ["{FIMS}positions","0","0"],
    ["{FIMS}plantilla_items","-2","0"],
    ["{FIMS}plantilla_items","0","0"],
    ["{FIMS}WorkflowManager.php","-2","0"],
    ["{FIMS}WorkflowManager.php","0","0"],
    ["{FIMS}DocumentTypeEditor.php","-2","0"],
    ["{FIMS}DocumentTypeEditor.php","0","0"],
    ["{FIMS}DocumentTypeManager.php","-2","0"],
    ["{FIMS}DocumentTypeManager.php","0","0"],
    ["{FIMS}IDGenerator.php","-2","0"],
    ["{FIMS}IDGenerator.php","0","0"],
    ["{FIMS}DepartmentManagement.php","-2","0"],
    ["{FIMS}DepartmentManagement.php","0","0"],
    ["{FIMS}id_format_configs","-2","0"],
    ["{FIMS}id_format_configs","0","0"],
    ["{FIMS}id_reset_strategies","-2","0"],
    ["{FIMS}id_reset_strategies","0","0"],
    ["{FIMS}id_sequences","-2","0"],
    ["{FIMS}id_sequences","0","0"],
    ["{FIMS}EventPlayground.php","-2","0"],
    ["{FIMS}EventPlayground.php","0","0"],
    ["{FIMS}department_systems","-2","0"],
    ["{FIMS}department_systems","0","0"],
    ["{FIMS}offices","-2","0"],
    ["{FIMS}offices","0","0"],
    ["{FIMS}user_offices","-2","0"],
    ["{FIMS}user_offices","0","0"],
    ["{FIMS}v_department_hierarchy","-2","0"],
    ["{FIMS}v_department_hierarchy","0","0"],
    ["{FIMS}v_user_access_summary","-2","0"],
    ["{FIMS}v_user_access_summary","0","0"],
    ["{FIMS}PositionManagement.php","-2","0"],
    ["{FIMS}PositionManagement.php","0","0"],
    ["{FIMS}SignatoryManagement.php","-2","0"],
    ["{FIMS}SignatoryManagement.php","0","0"],
    ["{FIMS}api_logs","-2","0"],
    ["{FIMS}api_logs","0","0"],
    ["{FIMS}api_resources","-2","0"],
    ["{FIMS}api_resources","0","0"],
    ["{FIMS}api_systems","-2","0"],
    ["{FIMS}api_systems","0","0"],
    ["{FIMS}document_asset_types","-2","0"],
    ["{FIMS}document_asset_types","0","0"],
    ["{FIMS}document_header_assets","-2","0"],
    ["{FIMS}document_header_assets","0","0"],
    ["{FIMS}document_header_slots","-2","0"],
    ["{FIMS}document_header_slots","0","0"],
    ["{FIMS}jwt_sessions","-2","0"],
    ["{FIMS}jwt_sessions","0","0"]];

/**
 * Tables
 *
 * @var array<string, string, string, bool, string>
 * [0] string Table name
 * [1] string Table variable name
 * [2] string Table caption
 * [3] bool Allowed for update (for userpriv.php)
 * [4] string Project ID
 * [5] string URL (for OthersController::index)
 */
$USER_LEVEL_TABLES = [["audit_logs","audit_logs","Audit Logs",false,"{FIMS}",""],
    ["systems","systems","Systems",true,"{FIMS}","SystemsList"],
    ["user_level_assignments","user_level_assignments","User Level Assignments",true,"{FIMS}","UserLevelAssignmentsList"],
    ["user_level_permissions","user_level_permissions","user level permissions",false,"{FIMS}","UserLevelPermissionsList"],
    ["user_levels","_user_levels","User Levels",true,"{FIMS}","UserLevelsList"],
    ["users","users","Users",true,"{FIMS}","UsersList"],
    ["aggregated_audit_logs","aggregated_audit_logs","Audit Logs",true,"{FIMS}","AggregatedAuditLogsList"],
    ["MainDashboard.php","MainDashboard","Dashboard",true,"{FIMS}","MainDashboard"],
    ["departments","departments","Department",true,"{FIMS}","DepartmentsList"],
    ["notifications","notifications","Notification",false,"{FIMS}","NotificationsList"],
    ["UserManagement.php","UserManagement","User Level Management",true,"{FIMS}","UserManagement"],
    ["UserAccess.php","UserAccess","User Access Management",true,"{FIMS}","UserAccess"],
    ["psgc","psgc","PH Standard GeoCode",true,"{FIMS}","PsgcList"],
    ["PSGCMap.php","PSGCMap","PSGC Map",true,"{FIMS}","PsgcMap"],
    ["FileManager.php","FileManager","File Manager",true,"{FIMS}","FileManager"],
    ["BullMQManager.php","BullMQManager","BullMQ Manager",true,"{FIMS}","BullMqManager"],
    ["PageEditor.php","PageEditor","Editor",true,"{FIMS}","PageEditor"],
    ["file_share_activity","file_share_activity","file share activity",true,"{FIMS}",""],
    ["file_share_permissions","file_share_permissions","file share permissions",true,"{FIMS}",""],
    ["file_shares","file_shares","file shares",true,"{FIMS}",""],
    ["shared_folder_access","shared_folder_access","shared folder access",true,"{FIMS}",""],
    ["AiAssistant.php","AiAssistant","eAssist Chat Playground",true,"{FIMS}","AiAssistant"],
    ["chat_history","chat_history","chat history",true,"{FIMS}",""],
    ["chat_sessions","chat_sessions","chat sessions",true,"{FIMS}",""],
    ["position_categories","position_categories","position categories",true,"{FIMS}","PositionCategoriesList"],
    ["positions","positions","positions",true,"{FIMS}","PositionsList"],
    ["plantilla_items","plantilla_items","plantilla items",true,"{FIMS}","PlantillaItemsList"],
    ["WorkflowManager.php","WorkflowManager","Workflow Manager",true,"{FIMS}","WorkflowManager"],
    ["DocumentTypeEditor.php","DocumentTypeEditor","Document Type Editor",true,"{FIMS}","DocumentTypeEditor"],
    ["DocumentTypeManager.php","DocumentTypeManager","Document Type Manager",true,"{FIMS}","DocumentTypeManager"],
    ["IDGenerator.php","IDGenerator","ID Generator Management",true,"{FIMS}","IdGenerator"],
    ["DepartmentManagement.php","DepartmentManagement","Department Management",true,"{FIMS}","DepartmentManagement"],
    ["id_format_configs","id_format_configs","id format configs",true,"{FIMS}",""],
    ["id_reset_strategies","id_reset_strategies","id reset strategies",true,"{FIMS}",""],
    ["id_sequences","id_sequences","id sequences",true,"{FIMS}",""],
    ["EventPlayground.php","EventPlayground","Event Playground",true,"{FIMS}","EventPlayground"],
    ["department_systems","department_systems","department systems",true,"{FIMS}","DepartmentSystemsList"],
    ["offices","offices","offices",true,"{FIMS}","OfficesList"],
    ["user_offices","user_offices","user offices",true,"{FIMS}","UserOfficesList"],
    ["v_department_hierarchy","v_department_hierarchy","v department hierarchy",true,"{FIMS}","VDepartmentHierarchyList"],
    ["v_user_access_summary","v_user_access_summary","v user access summary",true,"{FIMS}","VUserAccessSummaryList"],
    ["PositionManagement.php","PositionManagement","PositionManagement",true,"{FIMS}","PositionManagement"],
    ["SignatoryManagement.php","SignatoryManagement","Signatory Management",true,"{FIMS}","SignatoryManagement"],
    ["api_logs","api_logs","api logs",true,"{FIMS}","ApiLogsList"],
    ["api_resources","api_resources","api resources",true,"{FIMS}","ApiResourcesList"],
    ["api_systems","api_systems","api systems",true,"{FIMS}","ApiSystemsList"],
    ["document_asset_types","document_asset_types","document asset types",true,"{FIMS}","DocumentAssetTypesList"],
    ["document_header_assets","document_header_assets","document header assets",true,"{FIMS}","DocumentHeaderAssetsList"],
    ["document_header_slots","document_header_slots","document header slots",true,"{FIMS}","DocumentHeaderSlotsList"],
    ["jwt_sessions","jwt_sessions","jwt sessions",true,"{FIMS}","JwtSessionsList"]];
