# Workflow Manager API Mock Responses

This directory contains refactored API mock responses for the Workflow Manager system. The original `mock-workflows.json` has been broken down into realistic API endpoints that follow RESTful conventions.

## API Endpoints Structure

### 1. Workspaces
- **File**: `workspaces.json`
- **Endpoint**: `GET /api/workspaces`
- **Description**: List all workspaces with minimal fields for overview
- **Fields**: `id`, `name`, `description`, `icon`, `status`, `workflowCount`, `lastUpdated`

### 2. Workflows
- **File**: `workflows.json`
- **Endpoint**: `GET /api/workflows`
- **Description**: List all workflows with summary information
- **Fields**: `id`, `name`, `category`, `description`, `icon`, `status`, `workspaceId`, `stepCount`, `createdDate`, `updatedDate`

### 3. Workflow Detail
- **File**: `workflow-detail.json`
- **Endpoint**: `GET /api/workflows/{id}`
- **Description**: Get complete workflow details including inputs, steps, and configurations
- **Fields**: Full workflow object with `inputs`, `steps`, `statuses`, `mappings`, `outputs`

### 4. Workflow Steps
- **File**: `workflow-steps.json`
- **Endpoint**: `GET /api/workflows/{id}/steps`
- **Description**: Get steps for a specific workflow
- **Fields**: Workflow steps with `assignedTo`, `mapping`, `outputs`, `statuses`

### 5. Metadata Endpoints

#### Schemas
- **File**: `metadata-schemas.json`
- **Endpoint**: `GET /api/metadata/schemas`
- **Description**: Database schemas and table structures
- **Fields**: Schema definitions with tables and field descriptions

#### User Levels
- **File**: `metadata-user-levels.json`
- **Endpoint**: `GET /api/metadata/user-levels`
- **Description**: User permission levels and roles
- **Fields**: User level definitions with permissions and priorities

#### Departments
- **File**: `metadata-departments.json`
- **Endpoint**: `GET /api/metadata/departments`
- **Description**: Organizational departments and their workflows
- **Fields**: Department information with workflow assignments

## Response Format

All API responses follow a consistent format:

```json
{
  "success": true,
  "message": "Description of the operation",
  "data": { /* Response data */ },
  "pagination": { /* Pagination info for list endpoints */ }
}
```

## Usage for Mock API

These files can be used to:

1. **Frontend Development**: Mock API responses for UI development
2. **API Design**: Reference for actual API implementation
3. **Testing**: Consistent test data for integration tests
4. **Documentation**: Clear examples of expected API responses

## Benefits of Refactoring

1. **Realistic API Structure**: Mimics actual REST API responses
2. **Maintainable**: Separate files for different endpoints
3. **Scalable**: Easy to add new endpoints or modify existing ones
4. **Consistent**: Standardized response format across all endpoints
5. **Minimal Data**: List endpoints only include necessary fields
6. **Complete Data**: Detail endpoints include full information

## Next Steps

1. Use these files as templates for actual API implementation
2. Add validation schemas based on these responses
3. Create API documentation using these examples
4. Implement mock server using these JSON files
5. Add error response examples for different scenarios
