# Custom API Parameters Configuration Guide

This guide explains how to customize API parameters, field mappings, and payload structure to match your specific API requirements.

---

## Table of Contents

1. [Custom Parameters](#custom-parameters)
2. [Field Mapping](#field-mapping)
3. [Field Filtering](#field-filtering)
4. [Complete Examples](#complete-examples)
5. [Use Cases](#use-cases)

---

## Custom Parameters

Add custom parameters that will be included in every API request.

### Configuration

```json
{
    "api": {
        "custom_params": {
            "company_id": "COMP001",
            "location": "Head Office",
            "api_version": "v1",
            "source": "zkteco_service"
        }
    }
}
```

### Request Payload

```json
{
    "device_name": "Main Gate",
    "timestamp": "2026-01-19 06:30:00",
    "records": [...],
    "company_id": "COMP001",
    "location": "Head Office",
    "api_version": "v1",
    "source": "zkteco_service"
}
```

### Common Use Cases

- **Multi-tenant systems**: Add `tenant_id`, `organization_id`
- **Tracking**: Add `source`, `version`, `environment`
- **Authorization**: Add `client_id`, `api_key`
- **Business logic**: Add `department`, `branch`, `cost_center`

---

## Field Mapping

Rename fields to match your API's expected field names.

### Configuration

```json
{
    "api": {
        "field_mapping": {
            "uid": "employee_uid",
            "id": "employee_id",
            "timestamp": "attendance_time",
            "state": "auth_method",
            "type": "attendance_type",
            "device_name": "device_identifier"
        }
    }
}
```

### Default Fields (Before Mapping)

```json
{
    "uid": 1,
    "id": "EMP001",
    "timestamp": "2026-01-19 08:15:32",
    "state": 1,
    "state_name": "Fingerprint",
    "type": 0,
    "type_name": "Check-in",
    "device_name": "Main Gate",
    "synced_at": "2026-01-19 08:20:00"
}
```

### After Field Mapping

```json
{
    "employee_uid": 1,
    "employee_id": "EMP001",
    "attendance_time": "2026-01-19 08:15:32",
    "auth_method": 1,
    "state_name": "Fingerprint",
    "attendance_type": 0,
    "type_name": "Check-in",
    "device_identifier": "Main Gate",
    "synced_at": "2026-01-19 08:20:00"
}
```

### Available Fields for Mapping

| Original Field | Description | Example Value |
|----------------|-------------|---------------|
| `uid` | User ID from device | `1` |
| `id` | Employee/User ID | `"EMP001"` |
| `user_id` | Same as `id` | `"EMP001"` |
| `timestamp` | Attendance time | `"2026-01-19 08:15:32"` |
| `state` | Auth method code | `1` (0=Password, 1=Fingerprint, 2=Card) |
| `state_name` | Auth method name | `"Fingerprint"` |
| `type` | Attendance type code | `0` (0=Check-in, 1=Check-out, 4=OT-in, 5=OT-out) |
| `type_name` | Attendance type name | `"Check-in"` |
| `device_name` | Device identifier | `"Main Gate"` |
| `synced_at` | Sync timestamp | `"2026-01-19 08:20:00"` |

---

## Field Filtering

Control which fields are sent to the API.

### Include Fields (Whitelist)

Send **only** specified fields:

```json
{
    "api": {
        "include_fields": [
            "employee_id",
            "attendance_time",
            "attendance_type",
            "device_identifier"
        ]
    }
}
```

**Result**: Only these 4 fields will be sent, all others are removed.

### Exclude Fields (Blacklist)

Remove specific fields from payload:

```json
{
    "api": {
        "exclude_fields": [
            "password",
            "synced_at",
            "uid"
        ]
    }
}
```

**Result**: These fields will be removed, all others are kept.

### Priority

1. First: Field mapping is applied
2. Second: Include fields filter (if defined)
3. Third: Exclude fields filter (if defined)

---

## Complete Examples

### Example 1: ERP Integration (SAP/Oracle)

```json
{
    "api": {
        "endpoint": "https://erp.company.com/api/hr/attendance",
        "headers": {
            "Authorization": "Basic dXNlcjpwYXNz",
            "SAP-Client": "800"
        },
        "custom_params": {
            "plant_code": "P1000",
            "cost_center": "CC1001",
            "personnel_area": "PA01"
        },
        "field_mapping": {
            "id": "personnel_number",
            "timestamp": "attendance_datetime",
            "type": "attendance_type_id",
            "device_name": "recording_device"
        },
        "exclude_fields": ["uid", "synced_at"]
    }
}
```

**Payload sent to API**:
```json
{
    "device_name": "Main Gate",
    "timestamp": "2026-01-19 06:30:00",
    "plant_code": "P1000",
    "cost_center": "CC1001",
    "personnel_area": "PA01",
    "records": [
        {
            "personnel_number": "EMP001",
            "attendance_datetime": "2026-01-19 08:15:32",
            "attendance_type_id": 0,
            "type_name": "Check-in",
            "recording_device": "Main Gate",
            "state": 1,
            "state_name": "Fingerprint"
        }
    ]
}
```

---

### Example 2: Multi-Tenant SaaS

```json
{
    "api": {
        "endpoint": "https://api.hr-saas.com/v1/attendance",
        "headers": {
            "Authorization": "Bearer YOUR_TOKEN",
            "X-Tenant-ID": "tenant_123"
        },
        "custom_params": {
            "tenant_id": "tenant_123",
            "organization_id": "org_456",
            "workspace_id": "ws_789"
        },
        "field_mapping": {
            "id": "user_id",
            "timestamp": "event_time",
            "type": "event_type"
        },
        "include_fields": [
            "user_id",
            "event_time",
            "event_type",
            "device_name"
        ]
    }
}
```

**Payload sent to API**:
```json
{
    "tenant_id": "tenant_123",
    "organization_id": "org_456",
    "workspace_id": "ws_789",
    "records": [
        {
            "user_id": "EMP001",
            "event_time": "2026-01-19 08:15:32",
            "event_type": 0,
            "device_name": "Main Gate"
        }
    ]
}
```

---

### Example 3: Minimal Payload

```json
{
    "api": {
        "endpoint": "https://simple-api.com/punch",
        "custom_params": {
            "client_id": "CLIENT_001"
        },
        "field_mapping": {
            "id": "emp_id",
            "timestamp": "time",
            "type": "action"
        },
        "include_fields": ["emp_id", "time", "action"]
    }
}
```

**Payload sent to API**:
```json
{
    "client_id": "CLIENT_001",
    "records": [
        {
            "emp_id": "EMP001",
            "time": "2026-01-19 08:15:32",
            "action": 0
        }
    ]
}
```

---

### Example 4: Legacy System

```json
{
    "api": {
        "endpoint": "http://legacy.local/import",
        "method": "PUT",
        "custom_params": {
            "IMPORT_SOURCE": "BIOMETRIC",
            "IMPORT_USER": "SYSTEM"
        },
        "field_mapping": {
            "id": "EMP_NO",
            "timestamp": "PUNCH_TIME",
            "type": "PUNCH_TYPE",
            "device_name": "TERM_ID"
        },
        "exclude_fields": [
            "uid",
            "state",
            "state_name",
            "type_name",
            "synced_at"
        ]
    }
}
```

---

## Use Cases

### 1. **Cloud HR Systems** (BambooHR, Workday, etc.)

```json
{
    "custom_params": {
        "company_subdomain": "your-company",
        "api_key_owner": "hr_admin"
    },
    "field_mapping": {
        "id": "employee_id",
        "timestamp": "clock_in_time"
    }
}
```

### 2. **Webhook/Event Systems**

```json
{
    "custom_params": {
        "event_type": "attendance.recorded",
        "webhook_version": "1.0"
    },
    "field_mapping": {
        "timestamp": "occurred_at",
        "type": "event_action"
    }
}
```

### 3. **Database Direct Import**

```json
{
    "custom_params": {
        "table_name": "attendance_log",
        "import_batch_id": "{{timestamp}}"
    },
    "field_mapping": {
        "id": "emp_code",
        "timestamp": "log_datetime",
        "type": "log_type"
    }
}
```

### 4. **Third-Party Integration (Zapier, Make, n8n)**

```json
{
    "custom_params": {
        "trigger_type": "attendance_sync",
        "app_name": "zkteco"
    },
    "include_fields": [
        "employee_id",
        "timestamp",
        "type_name",
        "device_name"
    ]
}
```

---

## Testing Your Configuration

After configuring custom parameters, test with:

```cmd
php php\php.exe test_api.php
```

This will:
1. Show your configured custom params
2. Apply field mapping
3. Send test data to your API
4. Display the actual payload sent

---

## Dynamic Values

Some special placeholders you can use:

| Placeholder | Replaced With | Example |
|-------------|---------------|---------|
| `{{timestamp}}` | Current timestamp | `"2026-01-19 06:30:00"` |
| `{{date}}` | Current date | `"2026-01-19"` |
| `{{device_name}}` | Device identifier | `"Main Gate"` |

*Note: Implement in ApiClient.php if needed*

---

## Best Practices

1. **Use Field Mapping** for API compatibility
2. **Use Custom Params** for authentication/routing
3. **Use Include Fields** when API expects specific fields only
4. **Use Exclude Fields** to remove sensitive data
5. **Test thoroughly** with `test_api.php` before production

---

## Troubleshooting

### API Returns 400 (Bad Request)

1. Check field names match API documentation
2. Verify required fields are included
3. Check data types (strings, integers)

### API Returns 401 (Unauthorized)

1. Verify custom params contain correct auth info
2. Check headers are properly set
3. Ensure API token is valid

### Fields Missing in Payload

1. Check `include_fields` isn't too restrictive
2. Verify field mapping uses correct names
3. Ensure `exclude_fields` isn't removing needed fields

---

## Support

See complete examples in:
- `config_examples.json` - 10 real-world examples
- `README_API.md` - Full API documentation
- `test_api.php` - Testing tool

For issues, check:
```cmd
type logs\service.log
```
