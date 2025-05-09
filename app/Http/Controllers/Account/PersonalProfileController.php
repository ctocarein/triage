<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PersonalProfile;

class PersonalProfileController extends Controller
{
    // This controller handles the personal profile of the authenticated user.
    // It allows the user to view, create, update, and delete their personal profile information.

    public function index(Request $request)
    {
        // Retrieve the authenticated user's personal profile
        $personalProfile = $request->user()->personalProfile;

        return response()->json($personalProfile);
    }

    // Store a newly created personal profile in storage.

    /**
     * Store a newly created personal profile in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

     public function store(Request $request)
     {
         // Validation des champs
         $validated = $request->validate([
             'first_name' => 'nullable|string|max:255',
             'last_name' => 'nullable|string|max:255',
             'date_of_birth' => 'required|date',
             'language' => 'required|string|max:255',
             'education_level' => 'required|string|in:none,primary,secondary,university', // ✅ validation safe
             'commune' => 'required|string|max:255',
         ]);
     
         $account = $request->user();
     
         // Vérifie si un profil existe déjà
         if ($account->personalProfile) {
             return response()->json([
                 'message' => 'Personal profile already exists. Please use update instead.'
             ], 409); // 409 = Conflict
         }
     
         // Créer un nouveau profil
         $profile = new PersonalProfile([
             'account_id' => $account->id,
             ...$validated
         ]);
     
         $profile->save();
     
         return response()->json($profile, 201);
     }
     
    // /**
    //  * Display the specified personal profile.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return \Illuminate\Http\Response
    //  */
    // public function show(Request $request)

     public function update(Request $request)
     {
         $validated = $request->validate([
             'first_name' => 'nullable|string|max:255',
             'last_name' => 'nullable|string|max:255',
             'date_of_birth' => 'required|date',
             'language' => 'required|string|max:255',
             'education_level' => 'required|string|in:none,primary,secondary,university',
             'commune' => 'required|string|max:255',
         ]);
     
         $account = $request->user();
         $profile = $account->personalProfile;
     
         if (!$profile) {
             return response()->json(['message' => 'No personal profile found.'], 404);
         }
     
         $profile->fill($validated)->save();
     
         return response()->json($profile);
     }

    /**
     * Remove the specified personal profile from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function destroy(Request $request)
    {
        // Delete the personal profile
        $personalProfile = $request->user()->personalProfile;
        if ($personalProfile) {
            $personalProfile->delete();
        }

        return response()->json(['message' => 'Personal profile deleted successfully.']);
    }
}
