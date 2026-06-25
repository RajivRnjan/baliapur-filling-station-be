<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lubricant;
use App\Models\DailySaleLubricant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LubricantController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $query = Lubricant::query();

            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            if ($request->has('stock_status') && $request->stock_status) {
                if ($request->stock_status === 'out_of_stock') {
                    $query->where('stock', 0);
                } elseif ($request->stock_status === 'low_stock') {
                    $query->where('stock', '<=', 5);
                } elseif ($request->stock_status === 'in_stock') {
                    $query->where('stock', '>', 0);
                }
            }

            $lubricants = $query->orderBy('name', 'asc')->orderBy('qty_name', 'asc')->paginate($perPage);
            return response()->json([
                'success' => true,
                'lubricants' => $lubricants
            ]);
        } catch (\Exception $e) {
            Log::error('Lubricant Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lubricants.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'qty_name' => 'required|string|max:100',
                'price' => 'required|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
            ]);

            $data = $request->all();
            if (!isset($data['stock'])) {
                $data['stock'] = 0;
            }

            $lubricant = Lubricant::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Lubricant created successfully',
                'lubricant' => $lubricant
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Lubricant Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the lubricant.'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $lubricant = Lubricant::findOrFail($id);
            return response()->json([
                'success' => true,
                'lubricant' => $lubricant
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lubricant not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Lubricant Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lubricant.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can edit lubricants.'
                ], 403);
            }

            $lubricant = Lubricant::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'qty_name' => 'required|string|max:100',
                'price' => 'required|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
            ]);

            $lubricant->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Lubricant updated successfully',
                'lubricant' => $lubricant
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lubricant not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Lubricant Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the lubricant.'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can delete lubricants.'
                ], 403);
            }

            $lubricant = Lubricant::findOrFail($id);
            $lubricant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lubricant deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lubricant not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Lubricant Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the lubricant.'
            ], 500);
        }
    }

    public function addStock(Request $request, $id)
    {
        try {
            $request->validate([
                'quantity' => 'required|integer|min:1'
            ]);

            $lubricant = Lubricant::findOrFail($id);
            $lubricant->stock += $request->quantity;
            $lubricant->save();

            return response()->json([
                'success' => true,
                'message' => 'Stock added successfully',
                'lubricant' => $lubricant
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lubricant not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Lubricant Add Stock Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding stock.'
            ], 500);
        }
    }

    public function salesHistory(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            
            $query = \App\Models\DailySale::whereHas('lubricants')
                ->with(['lubricants.lubricant'])
                ->orderBy('date', 'desc');

            $history = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            Log::error('Lubricant Sales History Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lubricant sales history.'
            ], 500);
        }
    }
}
