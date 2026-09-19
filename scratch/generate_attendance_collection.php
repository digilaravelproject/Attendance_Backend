<?php

$source = 'C:/Users/Darshan Kondekar/Downloads/Attendance.postman_collection.json';
$target = __DIR__.'/../Attendance_updated.postman_collection.json';
$collection = json_decode(file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);

function headers(bool $json = false): array
{
    $items = [
        ['key' => 'Accept', 'value' => 'application/json'],
        ['key' => 'Authorization', 'value' => 'Bearer {{auth_secret_06hf}}'],
    ];
    if ($json) {
        $items[] = ['key' => 'Content-Type', 'value' => 'application/json'];
    }

    return $items;
}

function requestItem(string $name, string $method, string $path, ?array $body = null, ?array $tests = null): array
{
    $rawUrl = '{{base_url}}'.$path;
    $curl = "curl --request {$method} '{$rawUrl}' --header 'Accept: application/json' --header 'Authorization: Bearer {{auth_secret_06hf}}'";
    if ($body !== null) {
        $compact = json_encode($body, JSON_UNESCAPED_SLASHES);
        $curl .= " --header 'Content-Type: application/json' --data '{$compact}'";
    }
    $urlParts = parse_url(str_replace('{{base_url}}', 'http://placeholder.test', $rawUrl));
    $request = [
        'method' => $method,
        'header' => headers($body !== null),
        'url' => [
            'raw' => $rawUrl,
            'host' => ['{{base_url}}'],
            'path' => array_values(array_filter(explode('/', trim($urlParts['path'] ?? '', '/')))),
        ],
        'description' => "cURL:\n```bash\n{$curl}\n```",
    ];
    if (! empty($urlParts['query'])) {
        parse_str($urlParts['query'], $query);
        $request['url']['query'] = array_map(
            fn ($key, $value) => ['key' => (string) $key, 'value' => (string) $value],
            array_keys($query),
            array_values($query)
        );
    }
    if ($body !== null) {
        $request['body'] = [
            'mode' => 'raw',
            'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'options' => ['raw' => ['language' => 'json']],
        ];
    }

    $item = ['name' => $name, 'request' => $request, 'response' => []];
    if ($tests) {
        $item['event'] = [[
            'listen' => 'test',
            'script' => ['type' => 'text/javascript', 'exec' => $tests],
        ]];
    }

    return $item;
}

$departmentBody = [
    'name' => 'Engineering',
    'description' => 'Handles all engineering and product development activities.',
    'head_user_id' => 1,
    'employee_ids' => [1, 2, 3],
    'status' => 'Active',
];
$departmentFolder = [
    'name' => 'Department',
    'item' => [
        requestItem('Create Department', 'POST', '/api/admin/departments', $departmentBody, [
            'const json = pm.response.json();',
            "if (json.data && json.data.id) pm.collectionVariables.set('department_id', json.data.id);",
        ]),
        requestItem('Department List', 'GET', '/api/admin/departments?status=Active'),
        requestItem('Search Department', 'GET', '/api/admin/departments/search?query=Engineering'),
        requestItem('Get Department By ID', 'GET', '/api/admin/departments/{{department_id}}'),
        requestItem('Update Department', 'PATCH', '/api/admin/departments/{{department_id}}', [
            'name' => 'Product Engineering',
            'description' => 'Handles engineering, platform, and product development.',
            'head_user_id' => 1,
            'employee_ids' => [1, 2, 3],
            'status' => 'Active',
        ]),
        requestItem('Add Employees To Department', 'POST', '/api/admin/departments/{{department_id}}/employees', [
            'employee_ids' => [1, 2, 3],
        ]),
        requestItem('Remove Employee From Department', 'DELETE', '/api/admin/departments/{{department_id}}/employees/{{employee_id}}'),
        requestItem('Delete Department', 'DELETE', '/api/admin/departments/{{department_id}}'),
    ],
];

$leaveFolder = [
    'name' => 'Leave',
    'item' => [
        requestItem('Create Leave Type', 'POST', '/api/admin/leave-types', [
            'name' => 'Casual Leave',
            'code' => 'CL',
            'description' => 'Leave for personal work.',
            'annual_allowance' => 12,
            'is_paid' => true,
            'requires_attachment' => false,
            'status' => 'Active',
        ], [
            'const json = pm.response.json();',
            "if (json.data && json.data.id) pm.collectionVariables.set('leave_type_id', json.data.id);",
        ]),
        requestItem('Leave Type List', 'GET', '/api/admin/leave-types?status=Active'),
        requestItem('Get Leave Type By ID', 'GET', '/api/admin/leave-types/{{leave_type_id}}'),
        requestItem('Update Leave Type', 'PATCH', '/api/admin/leave-types/{{leave_type_id}}', [
            'annual_allowance' => 15,
            'requires_attachment' => false,
        ]),
        requestItem('Delete Leave Type', 'DELETE', '/api/admin/leave-types/{{leave_type_id}}'),
        requestItem('Create Leave Request', 'POST', '/api/admin/leave-requests', [
            'user_id' => 1,
            'leave_type_id' => '{{leave_type_id}}',
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-22',
            'reason' => 'Personal work at hometown.',
            'contact_during_leave' => '9876543210',
            'attachment_name' => 'Train_Ticket.pdf',
            'attachment_path' => 'leave-attachments/Train_Ticket.pdf',
        ], [
            'const json = pm.response.json();',
            "if (json.data && json.data.id) pm.collectionVariables.set('leave_request_id', json.data.id);",
        ]),
        requestItem('Leave Request List', 'GET', '/api/admin/leave-requests'),
        requestItem('Filter Leave Requests', 'GET', '/api/admin/leave-requests?status=Pending&date_range=this_month&department_id={{department_id}}&leave_type_id={{leave_type_id}}&search=Rahul'),
        requestItem('Get Leave Request By ID', 'GET', '/api/admin/leave-requests/{{leave_request_id}}'),
        requestItem('Update Pending Leave Request', 'PATCH', '/api/admin/leave-requests/{{leave_request_id}}', [
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-23',
            'reason' => 'Updated personal leave reason.',
        ]),
        requestItem('Approve Leave Request', 'POST', '/api/admin/leave-requests/{{leave_request_id}}/approve', [
            'note' => 'Approved by reporting manager.',
        ]),
        requestItem('Reject Leave Request', 'POST', '/api/admin/leave-requests/{{leave_request_id}}/reject', [
            'note' => 'Insufficient supporting information.',
        ]),
        requestItem('Leave Calendar', 'GET', '/api/admin/leave-requests/calendar?month=2026-09&department_id={{department_id}}'),
        requestItem('Leave Reports', 'GET', '/api/admin/leave-requests/reports?from_date=2026-01-01&to_date=2026-12-31'),
        requestItem('Delete Leave Request', 'DELETE', '/api/admin/leave-requests/{{leave_request_id}}'),
    ],
];

$adminIndex = null;
foreach ($collection['item'] as $index => $folder) {
    if (($folder['name'] ?? '') === 'Admin/Manager') {
        $adminIndex = $index;
        break;
    }
}
if ($adminIndex === null) {
    throw new RuntimeException('Admin/Manager folder not found in source collection.');
}
$collection['item'][$adminIndex]['item'] = array_values(array_filter(
    $collection['item'][$adminIndex]['item'],
    fn ($folder) => ! in_array($folder['name'] ?? '', ['Department', 'Leave'], true)
));
$collection['item'][$adminIndex]['item'][] = $departmentFolder;
$collection['item'][$adminIndex]['item'][] = $leaveFolder;

$variables = [
    'department_id' => '',
    'employee_id' => '',
    'leave_type_id' => '',
    'leave_request_id' => '',
];
$existingKeys = array_column($collection['variable'] ?? [], 'key');
foreach ($variables as $key => $value) {
    if (! in_array($key, $existingKeys, true)) {
        $collection['variable'][] = ['key' => $key, 'value' => $value];
    }
}

file_put_contents($target, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo $target.PHP_EOL;
