<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Nozzle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NozzleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $query = Nozzle::query();

            if ($request->has('fuel_type') && $request->fuel_type) {
                $query->where('fuel_type', $request->fuel_type);
            }

            $nozzles = $query->orderBy('nozzle_no', 'asc')->paginate($perPage);
            return response()->json([
                'success' => true,
                'nozzles' => $nozzles
            ]);
        } catch (\Exception $e) {
            Log::error('Nozzle Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve nozzles.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nozzle_no' => 'required|unique:nozzles,nozzle_no',
                'fuel_type' => 'required|in:MS,HSD,XP',
            ]);

            $nozzle = Nozzle::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Nozzle created successfully',
                'nozzle' => $nozzle
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Nozzle Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the nozzle.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can edit nozzles.'
                ], 403);
            }

            $nozzle = Nozzle::findOrFail($id);

            $request->validate([
                'nozzle_no' => 'required|unique:nozzles,nozzle_no,' . $id,
                'fuel_type' => 'required|in:MS,HSD,XP',
            ]);

            $nozzle->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Nozzle updated successfully',
                'nozzle' => $nozzle
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nozzle not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Nozzle Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the nozzle.'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can delete nozzles.'
                ], 403);
            }

            $nozzle = Nozzle::findOrFail($id);
            $nozzle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Nozzle deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nozzle not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Nozzle Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the nozzle.'
            ], 500);
        }
    }
}
