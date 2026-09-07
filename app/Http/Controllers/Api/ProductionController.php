<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Production;
use App\Models\Payment;
use App\Models\Attendence;
use App\Models\User;
use App\Models\Employee;
use App\Models\Factory;
use App\Models\Machine;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class ProductionController extends Controller
{
    // Show all productions
    public function index()
    {
        $productions = Production::with(['factory', 'employee.user', 'machine'])->get();

        return response()->json($productions, 200);
    }

    // Add production employee
   public function store(Request $request)
{
    \Log::info('STORE HIT');
    $request->validate([
        'machine_id' => 'required|integer',
        'user_id' => 'required|integer', 
        'factory_id' => 'required|integer',
        'ready_production' => 'required|numeric',
        'waste_production' => 'required|numeric',
    ]);

    $factory = Factory::find($request->factory_id);
    if (!$factory) {
        return response()->json(['message' => 'Factory not found'], 404);
    }

    // find employee by user_id in employees table 
    $employee = Employee::where('user_id', $request->user_id)
        ->where('factory_id', $request->factory_id)
        ->first();

    if (!$employee) {
        return response()->json(['message' => 'Employee not found for this user/factory'], 404);
    }

    // employee only allowed to submit production during their shift time
    $isSelfSubmission = $request->user() && $request->user()->id == $request->user_id;

    if ($isSelfSubmission && !$this->isWithinShift($employee->shift_starttime, $employee->shift_endtime)) {
        return response()->json([
            'message' => "You can only submit production during your shift ({$employee->shift_starttime} - {$employee->shift_endtime})",
        ], 403);
    }
// Latest production record for this employee on this machine
    $ownLatest = Production::where('machine_id', $request->machine_id)
        ->where('employee_id', $employee->id)
        ->latest()
        ->first();

    if (!$ownLatest || !$ownLatest->batch_id) {
        return response()->json([
            'message' => 'No production batch assigned to this machine yet. Ask owner to assign a batch first.',
        ], 422);
    }

    $batchId     = $ownLatest->batch_id;
    $varietyType = $ownLatest->variety_type;
    $totalLength = $ownLatest->total_length;
     


    // find latest manager_id for this factory 
    $managerId = Production::where('factory_id', $request->factory_id)
        ->whereNotNull('manager_id')
        ->latest()
        ->value('manager_id');

    // remaining production check minus both employees record ready production
    $readySoFar = Production::where('machine_id', $request->machine_id)
        ->where('batch_id', $batchId)
        ->sum('ready_production');

    $wasteSoFar = Production::where('machine_id', $request->machine_id)
        ->where('batch_id', $batchId)
        ->sum('waste_production');

    $previousRemaining = max(0, $totalLength - ($readySoFar + $wasteSoFar));

    $newRemaining =
        $previousRemaining
        - $request->ready_production
        - $request->waste_production;

    if ($newRemaining < 0) {
        return response()->json([
            "message" => "Production limit exceeded — sirf $previousRemaining hi remaining hai (dono shifts mila kar)"
        ], 400);
    }

    //  Alert threshold batch assign hote waqt set hui then is batch ki har row par carry hoti hai
    $alertThreshold = $ownLatest->alert_threshold;
    $alertAlreadySent = Production::where('machine_id', $request->machine_id)
        ->where('batch_id', $batchId)
        ->where('alert_sent', true)
        ->exists();

    $shouldSendAlert = $alertThreshold !== null
        && $newRemaining <= $alertThreshold
        && !$alertAlreadySent;
    $actingUser = $request->user();
    $enteredByOwner = $actingUser && method_exists($actingUser, 'hasRole') && $actingUser->hasRole('owner');
    $initialStatus = $enteredByOwner ? 4 : 1;

        $production = Production::create([
            'machine_id' => $request->machine_id,
            'employee_id' => $employee->id, 

            'factory_id' => $request->factory_id,
            'manager_id' => $managerId,

            'variety_type' => $varietyType,
            'total_length' => $totalLength,

            'ready_production' => $request->ready_production,
            'waste_production' => $request->waste_production,

            'remaining' => $newRemaining,
            'alert_threshold' => $alertThreshold,
            'alert_sent' => $shouldSendAlert,

            'batch_id' => $batchId,

            'shift_start' => $employee->shift_starttime,
            'shift_end' => $employee->shift_endtime,

            'status' => $initialStatus,
        ]);

        $machineName = Machine::where('id', $request->machine_id)->value('machine_name');
        $employeeName = $employee->user?->name ?? 'Employee';
        if (!$enteredByOwner) {
            try {
                $owners = User::role('owner')->get();
                foreach ($owners as $owner) {
                    Notification::create([
                        'user_id'       => $owner->id,
                        'production_id' => $production->id,
                        'sender_id'     => $request->user_id,
                        'title'         => 'New Production Submitted',
                        'message'       => "$employeeName submitted production on machine \"$machineName\" — pending approval",
                        'type'          => 'production_created',
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Owner notification create failed: ' . $e->getMessage());
            }
        }

        //  Low remaining alert machine 
        if ($shouldSendAlert) {
            try {
                $owners = User::role('owner')->get();
                foreach ($owners as $owner) {
                    Notification::create([
                        'user_id'       => $owner->id,
                        'production_id' => $production->id,
                        'sender_id'     => $request->user_id,
                        'title'         => 'Production Running Low',
                        'message'       => "Machine \"$machineName\" ($varietyType) has only $newRemaining meters remaining — nearing the total length limit.",
                        'type'          => 'low_remaining_alert',
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Low remaining alert notification failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Production submitted successfully',
            'data' => $production
        ]);
    }

    // Single production
    public function edit($id)
    {
        $production = Production::find($id);

        if (!$production) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($production, 200);
    }

    // Update production
    public function update(Request $request, $id)
    {
        $production = Production::find($id);

        if (!$production) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $production->update($request->all());

        return response()->json([
            'message' => 'Production updated successfully',
            'data' => $production
        ]);
    }

    // Delete production
    public function destroy($id)
    {
        $production = Production::find($id);

        if (!$production) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $production->delete();

        return response()->json([
            'message' => 'Production deleted successfully'
        ]);
    }
 
  
 // view-payments
public function viewPayments($factoryId)
{
    $authUser = auth()->user();

    $factory = Factory::find($factoryId);
    if (!$factory) {
        return response()->json(['message' => 'Factory not found'], 404);
    }

    $factoryName = $factory->name;

    $managerName = null;
    if ($factory->manager_id) {
        $manager = User::find($factory->manager_id);
        $managerName = $manager?->name;
    }

    // ROLE BASED ACCESS CONTROL
    if ($authUser->hasRole('owner')) {
        // Admin full access no restriction

    } elseif ($authUser->hasRole('manager')) {
        if ((int) $factory->manager_id !== (int) $authUser->id) {
            return response()->json([
                'message' => 'Unauthorized: You are not the manager of this factory.'
            ], 403);
        }

    } elseif ($authUser->hasRole('employee')) {
        // filtered below

    } else {
        return response()->json(['message' => 'Unauthorized role.'], 403);
    }
 

    $recordsQuery = Production::where('factory_id', $factoryId)
        ->whereNotNull('employee_id')
        ->orderBy('employee_id')
        ->orderBy('machine_id')
        ->orderByDesc('created_at');

    if ($authUser->hasRole('employee')) {
        $employee = Employee::where('user_id', $authUser->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $recordsQuery->where('employee_id', $employee->id);
    }

    $records = $recordsQuery->get();

    // Get total paid amount for each employee
    $employeeIds = $records->pluck('employee_id')->filter()->unique();

    $paidAmounts = Payment::whereIn('employee_id', $employeeIds)
        ->selectRaw('employee_id, SUM(amount_paid) as total_paid')
        ->groupBy('employee_id')
        ->pluck('total_paid', 'employee_id');


    // Fetch all machine names in a single query 
    $machineIds = $records->pluck('machine_id')->filter()->unique();
    $machines = Machine::whereIn('id', $machineIds)->pluck('machine_name', 'id');

    $grouped = $records->groupBy('employee_id')->map(function ($rows, $employeeId) use (
    $machines,
    $factoryName,
    $managerName,
    $paidAmounts
) {
    $employee = Employee::find($employeeId);

    $employeeName = null;

    if ($employee) {
        $user = User::find($employee->user_id);
        $employeeName = $user?->name;
    }

    // machine-wise grouping
    $machineGroups = $rows->groupBy('machine_id')->map(function ($machineRows, $machineId) use ($machines) {

        $productions = $machineRows->map(function ($row) {
            $totalLength = (float) ($row->total_length ?? 0);
            $rate        = (float) ($row->amount_per_meter ?? 0);
            $ready       = (float) ($row->ready_production ?? 0);
            $waste       = (float) ($row->waste_production ?? 0);
            $status      = (int) ($row->status ?? 1);

            $expectedAmount = $totalLength * $rate;

            // Sirf status = 4 (Owner Approved) par earned amount count hota hai
            $earnedAmount = 0.0;
            if ($status === 4) {
                if ($row->earned_amount !== null && (float)$row->earned_amount > 0) {
                    $earnedAmount = (float) $row->earned_amount;
                } else {
                    $earnedAmount = $ready * $rate;
                }
            }

            return [
                'production_id'        => $row->id,
                'batch_id'             => $row->batch_id,
                'variety_type'         => $row->variety_type,
                'status'               => $status,
                'total_length'         => $totalLength,
                'ready_production'     => (int) $ready,
                'waste_production'     => $waste,
                'remaining_production' => max(0, $totalLength - $ready - $waste),
                'amount_per_meter'     => $rate,
                'expected_amount'      => $expectedAmount,
                'earned_amount'        => $earnedAmount,
                'amount'               => $earnedAmount, // backward compatibility
                'select_days'          => $row->select_days,
                'shift_start'          => $row->shift_start,
                'shift_end'            => $row->shift_end,
                'created_at'           => $row->created_at,
            ];
        })->values();

        $expectedTotal = (float) $productions->sum('expected_amount');
        $earnedTotal   = (float) $productions->sum('earned_amount');

        return [
            'machine_id'           => $machineId ? (int) $machineId : null,
            'machine_name'         => $machines[$machineId] ?? 'Unassigned',
            'production_count'     => $productions->count(),
            'total_length'         => $productions->sum('total_length'),
            'ready_production'     => $productions->sum('ready_production'),
            'waste_production'     => $productions->sum('waste_production'),
            'remaining_production' => $productions->sum('remaining_production'),
            'expected_amount'      => $expectedTotal,
            'earned_amount'        => $earnedTotal,
            'total_amount'         => $earnedTotal, // backward compatibility
            'productions'          => $productions,
        ];
    })->values();

    // Total expected for all batches of this employee
    $totalExpected = (float) $machineGroups->sum('expected_amount');

    // Total earned from approved batches of this employee
    $totalEarned = (float) $machineGroups->sum('earned_amount');

    // Total already paid
    $totalPaid = (float) ($paidAmounts[$employeeId] ?? 0);

    // Remaining payable amount
    $remainingAmount = max(0, $totalEarned - $totalPaid);

    return [
        'employee_id'      => (int) $employeeId,
        'employee_name'    => $employeeName,
        'factory_name'     => $factoryName,
        'manager_name'     => $managerName,

        // Payment summary
        'total_expected'   => $totalExpected,
        'total_amount'     => $totalEarned,
        'total_earned'     => $totalEarned,
        'total_paid'       => $totalPaid,
        'remaining_amount' => $remainingAmount,

        'total_length'     => $machineGroups->sum('total_length'),
        'machines'         => $machineGroups,
    ];
})->values();


    return response()->json([
        'data' => $grouped,
    ]);
}
    //  Assign production (FIXED)
    public function assignProduction(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|integer',
            'variety_type' => 'required|string',
            'total_length' => 'required|numeric',
            'amount_per_meter' => 'required|numeric',
            //  owner gets notified once a batch's remaining length drops to this
            'alert_threshold' => 'nullable|numeric|min:0',
        ]);

        // assign batch to all employes that work on this machine 
        $employeeIds = Production::where('machine_id', $request->machine_id)
            ->whereNotNull('employee_id')
            ->distinct()
            ->pluck('employee_id');

        if ($employeeIds->isEmpty()) {
            return response()->json([
                'message' => 'First assign employees to this machine, then assign production batch.',
            ], 422);
        }

        $batchId = 'BATCH-' . $request->machine_id . '-' . time();

        $created = [];
        info($request);

        foreach ($employeeIds as $empId) {
            $employee = Employee::find($empId);
            if (!$employee) continue;

            $last = Production::where('machine_id', $request->machine_id)
                ->where('employee_id', $empId)
                ->latest()
                ->first();

            $production = Production::create([
                'batch_id' => $batchId,
                'machine_id' => $request->machine_id,
                'employee_id' => $employee->id,

                'factory_id' => $last?->factory_id,
                'manager_id' => $last?->manager_id,

                'variety_type' => $request->variety_type,
                'total_length' => $request->total_length,
                'amount_per_meter' => $request->amount_per_meter,
                'alert_threshold' => $request->alert_threshold,
                'alert_sent' => false,

                'ready_production' => 0,
                'waste_production' => 0,
                'remaining' => $request->total_length,

                'shift_start' => $employee->shift_starttime,
                'shift_end' => $employee->shift_endtime,

                'status' => 0,
            ]);

            $created[] = $production;   // append to the array for count() later

             $user_Name = User::whereId($employee->user_id)->first();
             Notification::create([
                    'user_id'       => $employee->user_id,
                    'production_id' => $production->id,
                    'sender_id'     => Auth::user()->id,
                    'title'         => 'New Production assigned',
                    'message'       => "$user_Name->name assigned production by ".Auth::user()->name,
                    'type'          => 'production_assigned',
                ]);
        }

        return response()->json([
            'message' => 'Production batch assigned to ' . count($created) . ' employee(s) successfully',
            'batch_id' => $batchId,
        ], 201);
    }

    // check the employee shift of current time
    private function isWithinShift($start, $end)
    {
        if (!$start || !$end) {
            return true;
        }

        $now   = \Carbon\Carbon::now()->format('H:i:s');
        $start = \Carbon\Carbon::parse($start)->format('H:i:s');
        $end   = \Carbon\Carbon::parse($end)->format('H:i:s');

        if ($start <= $end) {
            // Normal shift 
            return $now >= $start && $now <= $end;
        }

        // Overnight shift 
        return $now >= $start || $now <= $end;
    }
}