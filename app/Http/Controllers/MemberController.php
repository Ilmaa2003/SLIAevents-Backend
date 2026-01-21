<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function verifyMember($membership_no)
    {
        $member = DB::table('member_details')
            ->where('membership_no', $membership_no)
            ->first();

        if (!$member) {
            return response()->json([
                'valid' => false,
                'message' => 'Membership number not found. Please contact your organization to update your details.'
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'full_name' => $member->full_name ?? '',
            'email' => $member->personal_email ?? '',
            'mobile' => $member->personal_mobilenumer ?? '',
        ]);
    }
}
