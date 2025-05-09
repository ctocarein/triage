<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MedicalProfileController extends Controller
{
    //
    // This controller handles the medical profile of the authenticated user.
    // It allows the user to view, create, update, and delete their medical profile information.
    public function index(Request $request)
    {
        // Retrieve the authenticated user's medical profile
        $medicalProfile = $request->user()->medicalProfile;

        return response()->json($medicalProfile);
    }

    // Store a newly created medical profile in storage.


    /**
     * Store a newly created medical profile in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
        */      

        public function store(Request $request)
        {
            $validated = $request->validate([
                'cmu_number' => 'nullable|string|max:255',
                'height' => 'nullable|numeric|min:50|max:250',
                'weight' => 'nullable|numeric|min:20|max:300',
                'blood_type' => 'nullable|string|max:3',
                'allergies' => 'nullable|string|max:500',
                'is_pregnant' => 'boolean',
                'breastfeeding' => 'boolean',
                'menopause_status' => 'nullable|string|max:255',
                'family_cancer_history' => 'boolean',
                'last_screening_date' => 'nullable|date',
                'screening_type' => 'nullable|string|max:255',
                'first_sexual_intercourse_age' => 'nullable|integer|min:10|max:60',
                'number_of_partners' => 'nullable|integer|min:0|max:99',
                'number_of_pregnancies' => 'nullable|integer|min:0|max:20',
                'number_of_abortions' => 'nullable|integer|min:0|max:20',
                'number_of_children' => 'nullable|integer|min:0|max:20',
                'exposed_to_smoke' => 'boolean',
                'exposure_type' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
            ]);
    
            $account = $request->user();
    
            if ($account->medicalProfile) {
                return response()->json(['message' => 'Medical profile already exists. Use update instead.'], 409);
            }
    
            $profile = new MedicalProfile($validated + ['account_id' => $account->id]);
            $profile->save();
    
            return response()->json($profile, 201);
        }

        
}
