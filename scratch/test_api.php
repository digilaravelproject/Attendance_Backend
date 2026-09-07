<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;

$adminRole = Role::where('name', 'Admin')->first();
$roleId = $adminRole ? $adminRole->id : 1;

echo "--- Testing Role Listing (Requirement 5 / Screenshot 3) ---\n";
$roleController = new RoleController();
$res1 = $roleController->index(new Request());
echo json_encode(json_decode($res1->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "--- Testing Role Details for Admin (ID: {$roleId}) (Requirement 4 / Screenshot 2) ---\n";
$res2 = $roleController->show((string)$roleId);
echo json_encode(json_decode($res2->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "--- Testing Permission Matrix for Admin (ID: {$roleId}) (Requirement 2 / Screenshot 1) ---\n";
$permController = new PermissionController();
$res3 = $permController->index(new Request(['role_id' => $roleId]));
echo json_encode(json_decode($res3->getContent()), JSON_PRETTY_PRINT) . "\n\n";
