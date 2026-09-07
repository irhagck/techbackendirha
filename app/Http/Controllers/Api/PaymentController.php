<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Employee;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['employee.user', 'user', 'production']);

        if ($request->has('factory_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('factory_id', $request->factory_id);
            });
        }

        $payments = $query->latest()->get();

        return response()->json([
            'data' => $payments,
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount_paid'   => 'required|numeric|min:1',
            'employee_id'   => 'required|integer|exists:employees,id',
            // 'user_id'       => 'required|integer|exists:users,id',
            'production_id' => 'nullable|integer|exists:productions,id', 
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        if ($request->production_id) {
            $production = Production::where('id', $request->production_id)
                ->where('employee_id', $request->employee_id)
                ->first();

            if (!$production) {
                return response()->json([
                    'message' => 'This production does not belong to the selected employee',
                ], 422);
            }
        }

        $payment = Payment::create([
            'amount_paid'   => $request->amount_paid,
            'employee_id'   => $request->employee_id,
            'user_id'       => Auth::user()->id,
            'production_id' => $request->production_id, 
        ]);

        return response()->json([
            'message' => 'Payment saved successfully',
            'data'    => $payment->load(['employee.user', 'user', 'production']),
        ], 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load(['employee.user', 'user', 'production']));
    }

    public function edit(Payment $payment)
    {
        //
    }

    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'amount_paid' => 'sometimes|numeric|min:1',
        ]);

        $payment->update($request->only(['amount_paid']));

        return response()->json([
            'message' => 'Payment updated successfully',
            'data'    => $payment,
        ]);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}