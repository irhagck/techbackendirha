<?php
// app/Http/Controllers/ProductionController.php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Production;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\Factory;
use App\Models\Notification;
class ApproveProductionController extends Controller
{
    // STATUS REFERENCE:
    //   1 = employee submitted (pending)
    //   2 = manager approved
    //   3 = manager rejected
    //   4 = owner approved
    //   5 = owner rejected


    //MANAGER get all productions for factory 
  
public function managerProductions($factoryId)
{
   
    $factory = Factory::find($factoryId);

    if (!$factory) {
        return response()->json([
            'message' => 'Factory not found'
        ], 404);
    }

    // Isi factory ke employees
    $validEmployeeIds = Employee::where(
        'factory_id',
        $factoryId
    )->pluck('id');

    // Isi factory ki machines
    $validMachineIds = Machine::where(
        'factory_id',
        $factoryId
    )->pluck('id');

    // Productions
    $productions = Production::where(
            'factory_id',
            $factoryId
        )
        ->whereIn('status', [1, 2, 4])
        ->whereIn('employee_id', $validEmployeeIds)
        ->whereIn('machine_id', $validMachineIds)
        ->with([
            'employeedetails.user',
            'machineemploye'
        ])
        ->latest()
        ->get();

    return response()->json([
        'status' => true,
        'productions' => $productions
    ]);
}
    // MANAGER: approve or reject
   public function managerAction(Request $request, $id)
{
    $request->validate(['action' => 'required|in:approve,reject']);

    $prod = Production::with('employeedetails.user')->findOrFail($id);

    if ($prod->status == 4) {
        return response()->json([
            'message' => 'Owner has already approved this production',
            'production' => $prod,
        ]);
    }

    $prod->status = $request->action === 'approve' ? 2 : 3;
    $prod->save();

    $managerName = $request->user()->name ?? 'Manager';

    // notification employee ko bhejo
    $employeeUserId = $prod->employeedetails->user->id ?? null;

    if ($employeeUserId) {
        Notification::create([
            'user_id'        => $employeeUserId,
            'production_id'  => $prod->id,
            'sender_id'      => $request->user()->id,
            'title'          => $request->action === 'approve' ? 'Production Approved' : 'Production Rejected',
            'message'        => $request->action === 'approve'
                ? "Your production has been approved by $managerName"
                : "Your production has been rejected by $managerName",
            'type'           => $request->action === 'approve' ? 'approved' : 'rejected',
        ]);
    }

    // owner(s) ko bhi batao
    try {
        $machineName  = optional($prod->machineemploye)->machine_name ?? 'Machine';
        $employeeName = optional($prod->employeedetails)->user->name ?? 'Employee';

        $owners = \App\Models\User::role('owner')->get();
        foreach ($owners as $owner) {
            Notification::create([
                'user_id'       => $owner->id,
                'production_id' => $prod->id,
                'sender_id'     => $request->user()->id,
                'title'         => $request->action === 'approve' ? 'Manager Approved Production' : 'Manager Rejected Production',
                'message'       => $request->action === 'approve'
                    ? "$managerName approved $employeeName's production on \"$machineName\""
                    : "$managerName rejected $employeeName's production on \"$machineName\"",
                'type'          => $request->action === 'approve' ? 'approved' : 'rejected',
            ]);
        }
    } catch (\Exception $e) {
        \Log::error('Owner notification create failed: ' . $e->getMessage());
    }

    return response()->json([
        'message' => $request->action === 'approve' ? 'Approved' : 'Rejected',
        'production' => $prod,
    ]);
}
    // Owner get all productions for factory
 // app/Http/Controllers/Api/ApproveProductionController.php

public function ownerProductions($factoryId)
{
    $factory = Factory::find($factoryId);
    if (!$factory) {
        return response()->json(['message' => 'Factory not found'], 404);
    }

    $validEmployeeIds = Employee::where('factory_id', $factoryId)->pluck('id');
    $validMachineIds  = Machine::where('factory_id', $factoryId)->pluck('id');

    $productions = Production::where('factory_id', $factoryId)
        ->whereIn('status', [1, 2, 3, 4, 5]) // sab statuses, hum khud filter karenge
        ->whereIn('employee_id', $validEmployeeIds)
        ->whereIn('machine_id', $validMachineIds)
        ->with(['employeedetails.user', 'machineemploye'])
        ->latest()
        ->get();

    // Employee-wise group
    $employees = $productions->groupBy('employee_id')->map(function ($rows, $employeeId) {
        $first        = $rows->first();
        $employeeName = optional(optional($first->employeedetails)->user)->name
            ?? "Emp #$employeeId";

        // Machine-wise group (sirf un machines pe jin pe is employee ne production dala hai)
        $machineGroups = $rows->groupBy('machine_id')->map(function ($machineRows, $machineId) {
            $machineName = optional($machineRows->first()->machineemploye)->machine_name
                ?? "Machine #$machineId";

            $pendingRows  = $machineRows->whereNotIn('status', [4, 5]); // 1,2,3 = pending
            $approvedRows = $machineRows->where('status', 4);

            $mapProd = function ($p) {
                $total = (float) ($p->total_length ?? 0);
                $ready = (float) ($p->ready_production ?? 0);
                $waste = (float) ($p->waste_production ?? 0);

                return [
                    'id'                => $p->id,
                    'batch_id'          => $p->batch_id,
                    'variety_type'      => $p->variety_type,
                    'status'            => (int) $p->status,
                    'total_length'      => $total,
                    'ready_production'  => $ready,
                    'waste_production'  => $waste,
                    'remaining'         => max(0, $total - $ready - $waste),
                    'created_at'        => $p->created_at,
                    'updated_at'        => $p->updated_at,
                ];
            };

            return [
                'machine_id'       => $machineId ? (int) $machineId : null,
                'machine_name'     => $machineName,
                'pending_count'    => $pendingRows->count(),
                'approved_count'   => $approvedRows->count(),
                'production_count' => $machineRows->count(),
                'pending'          => $pendingRows->map($mapProd)->values(),
                'approved'         => $approvedRows->map($mapProd)->values(),
            ];
        })->values();

        return [
            'employee_id'    => (int) $employeeId,
            'employee_name'  => $employeeName,
            'machine_count'  => $machineGroups->count(),
            'pending_count'  => $machineGroups->sum('pending_count'),
            'approved_count' => $machineGroups->sum('approved_count'),
            'machines'       => $machineGroups,
        ];
    })->values();

    return response()->json([
        'status'    => true,
        'employees' => $employees,
    ]);
}
    //Owner approve or reject 
  public function ownerAction(Request $request, $id)
{
    $request->validate(['action' => 'required|in:approve,reject']);

    $prod = Production::with('employeedetails.user')->findOrFail($id);

    if ($request->action === 'approve') {
        $prod->status = 4;
        $prod->earned_amount = $prod->ready_production * $prod->amount_per_meter;
    } else {
        $prod->status = 5;
        $prod->earned_amount = 0;
    }
    $prod->save();

    $ownerName = $request->user()->name ?? 'Owner';

    // Notify the employee about the action
    try {
        $employeeUserId = $prod->employeedetails->user->id ?? null;

        if ($employeeUserId) {
            Notification::create([
                'user_id'        => $employeeUserId,
                'production_id'  => $prod->id,
                'sender_id'      => $request->user()->id,
                'title'          => $request->action === 'approve' ? 'Production Approved' : 'Production Rejected',
                'message'        => $request->action === 'approve'
                    ? "Your production has been approved by $ownerName"
                    : "Your production has been rejected by $ownerName",
                'type'           => $request->action === 'approve' ? 'approved' : 'rejected',
            ]);
        }
    } catch (\Exception $e) {
        \Log::error('Notification create failed: ' . $e->getMessage());
    }

    // Notify to also manager
    try {
        if ($prod->manager_id) {
            $machineName  = optional($prod->machineemploye)->machine_name ?? 'Machine';
            $employeeName = optional($prod->employeedetails)->user->name ?? 'Employee';

            Notification::create([
                'user_id'       => $prod->manager_id,
                'production_id' => $prod->id,
                'sender_id'     => $request->user()->id,
                'title'         => $request->action === 'approve' ? 'Owner Approved Production' : 'Owner Rejected Production',
                'message'       => $request->action === 'approve'
                    ? "$ownerName approved $employeeName's production on \"$machineName\""
                    : "$ownerName rejected $employeeName's production on \"$machineName\"",
                'type'          => $request->action === 'approve' ? 'approved' : 'rejected',
            ]);
        }
    } catch (\Exception $e) {
        \Log::error('Manager notification create failed: ' . $e->getMessage());
    }

    return response()->json([
        'message' => $request->action === 'approve' ? 'Owner Approved' : 'Owner Rejected',
        'production' => $prod,
    ]);
}
    
}