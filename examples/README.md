# RBAC Bundle - Extensible Role-Permission System

This document explains how to use the new extensible role-permission system that allows you to implement custom relationship patterns, including ABAC (Attribute-Based Access Control) with scoped permissions.

## Overview

The RBAC Bundle has been refactored to provide maximum flexibility while maintaining backward compatibility. The key changes include:

- **Separated Concerns**: Role entities no longer have hardcoded permission relationships
- **Trait-Based Approach**: Use `RolePermissionTrait` for backward compatibility
- **Interface-Driven**: Permission checking is abstracted through `RolePermissionCheckerInterface`
- **Custom Implementations**: Full control over role-permission relationships and join entities

## Architecture

### Core Components

1. **Role Entity**: Clean base class with only essential role functionality
2. **RolePermissionTrait**: Optional trait providing ManyToMany relationship (backward compatibility)
3. **RolePermissionInterface**: Interface for roles that manage permissions
4. **RolePermissionCheckerInterface**: Interface for permission checking logic
5. **DefaultRolePermissionChecker**: Default implementation using `role_permissions` table

## Usage Patterns

### Pattern 1: Backward Compatibility (Default Behavior)

For existing projects that want to maintain the original ManyToMany relationship:

```php
use PhpRbacBundle\Entity\DefaultRole;
use PhpRbacBundle\Entity\RolePermissionInterface;

#[ORM\Entity]
class MyRole extends DefaultRole
{
    // Automatically includes RolePermissionTrait
    // Uses role_permissions pivot table
    // No additional code needed
}
```

### Pattern 2: Custom OneToMany with ABAC Scoping

For projects that need custom join entities with additional data (like scope):

```php
use PhpRbacBundle\Entity\Role;

#[ORM\Entity]
class CustomRole extends Role
{
    #[ORM\OneToMany(targetEntity: CustomRolePermission::class, mappedBy: 'role')]
    private Collection $rolePermissions;

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
    }

    // Custom methods for managing scoped permissions
    public function hasPermissionWithScope(int $permissionId, ?string $scope = null): bool
    {
        // Your custom logic here
    }
}
```

### Pattern 3: Completely Custom Implementation

For projects with unique requirements:

```php
use PhpRbacBundle\Entity\Role;

#[ORM\Entity]
class AdvancedRole extends Role
{
    // Your completely custom relationship structure
    // Could be ManyToMany with additional pivot data
    // Could be through intermediate entities
    // Could integrate with external systems
}
```

## Implementation Guide

### Step 1: Choose Your Pattern

Decide which pattern fits your needs:
- **Simple**: Use `DefaultRole` (backward compatible)
- **ABAC**: Use custom OneToMany with scope
- **Advanced**: Implement completely custom solution

### Step 2: Create Your Entities

#### For ABAC Pattern:

**CustomRole.php** (see examples/CustomRoleImplementation.php)
- Extends base `Role` class
- Defines OneToMany relationship to join entity
- Implements custom permission checking methods

**CustomRolePermission.php** (see examples/CustomRolePermission.php)
- Custom join entity with scope field
- Additional metadata fields as needed
- Methods for scope matching and validation

### Step 3: Implement Permission Checker

**CustomRolePermissionChecker.php** (see examples/CustomRolePermissionChecker.php)
- Implements `RolePermissionCheckerInterface`
- Handles scope-based permission queries
- Maintains nested set functionality for hierarchical permissions

### Step 4: Configure Services

Update your `services.yaml`:

```yaml
# Replace the default permission checker with your custom one
PhpRbacBundle\Core\RolePermissionCheckerInterface: '@App\Service\CustomRolePermissionChecker'

# Register your custom checker
App\Service\CustomRolePermissionChecker: ~
```

### Step 5: Update Entity Configuration

Configure Doctrine to use your custom role entity:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        resolve_target_entities:
            PhpRbacBundle\Entity\RoleInterface: App\Entity\CustomRole
```

## ABAC Scoping Examples

### Organizational Scoping

```php
// Grant permission only within specific organization
$rolePermission = new CustomRolePermission();
$rolePermission->setRole($role);
$rolePermission->setPermission($permission);
$rolePermission->setScope('organization:123');

// Check permission with scope
$hasPermission = $permissionChecker->hasPermissionWithScope(
    $roleId, 
    $permissionId, 
    'organization:123'
);
```

### Hierarchical Scoping

```php
// Department-level permission
$rolePermission->setScope('department:sales');

// Project-level permission
$rolePermission->setScope('project:456');

// Check with pattern matching
$hasPermission = $role->hasPermissionWithScope($permissionId, 'department:sales');
```

### Time-Limited Permissions

```php
// Permission expires after 30 days
$rolePermission->setExpiresAt(new \DateTime('+30 days'));

// Checker automatically validates expiration
$isValid = $rolePermission->isValid();
```

## Migration from Original System

### For Existing Projects

1. **Assess Current Usage**: Determine if you need custom functionality
2. **Choose Migration Path**: 
   - Minimal change: Switch to `DefaultRole`
   - Custom needs: Implement ABAC pattern
3. **Update Entity Classes**: Extend from appropriate base class
4. **Test Thoroughly**: Ensure all permission checks still work
5. **Gradual Migration**: Can coexist during transition period

### Database Considerations

- **Backward Compatible**: `role_permissions` table still works with `DefaultRole`
- **Custom Tables**: Create new tables for custom join entities
- **Migration Scripts**: May need to migrate data between table structures

## Best Practices

### Security

- Always validate scope parameters in permission checkers
- Use parameterized queries to prevent SQL injection
- Implement proper access controls for scope management
- Consider caching for performance-critical permission checks

### Performance

- Index scope columns for fast lookups
- Use database-level constraints for data integrity
- Consider read replicas for permission checking queries
- Implement caching strategies for frequently checked permissions

### Maintainability

- Document your scope patterns and conventions
- Use consistent naming for scope values
- Implement validation for scope formats
- Create helper methods for common permission patterns

## Troubleshooting

### Common Issues

1. **Service Not Found**: Ensure your custom permission checker is registered
2. **Interface Conflicts**: Make sure role implements correct interfaces
3. **Database Errors**: Check table names and column mappings
4. **Permission Denied**: Verify scope matching logic

### Debugging

- Enable SQL logging to see generated queries
- Use profiler to identify performance bottlenecks
- Add logging to permission checker methods
- Test with simple cases before complex scenarios

## Advanced Features

### Custom Metadata

Store additional context in the join entity:

```php
$rolePermission->setMetadata([
    'granted_by' => $userId,
    'granted_at' => new \DateTime(),
    'reason' => 'Project assignment',
    'conditions' => ['ip_range' => '192.168.1.0/24']
]);
```

### Dynamic Scoping

Implement runtime scope resolution:

```php
public function hasPermissionInContext(int $permissionId, array $context): bool
{
    $scope = $this->buildScopeFromContext($context);
    return $this->hasPermissionWithScope($permissionId, $scope);
}
```

### Integration with External Systems

Connect to external authorization services:

```php
public function hasPermission(int $roleId, int $permissionId): bool
{
    // Check local permissions first
    $hasLocal = $this->checkLocalPermissions($roleId, $permissionId);
    
    // Fallback to external system
    if (!$hasLocal) {
        return $this->externalAuthService->checkPermission($roleId, $permissionId);
    }
    
    return $hasLocal;
}
```

This extensible system provides the flexibility to implement any permission model while maintaining the robust nested set functionality that makes this RBAC bundle powerful.
