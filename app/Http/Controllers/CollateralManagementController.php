<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

class CollateralManagementController extends Controller
{
    public function create(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'member' => 'bail|required|exists:members,uid',
            'type' => 'bail|required|string|in:collateral,return',
            'pic' => 'bail|required|string|max:100',
            'document_number' => 'bail|nullable|string|max:50',
            'member_name' => 'bail|required|string|max:100',
            'member_address' => 'bail|nullable|string|max:200',
            'signatory_status' => 'bail|nullable|string|max:50',
            'company_name' => 'bail|nullable|string|max:100',
            'contact_person' => 'bail|nullable|string|max:100',
            'payment_method' => 'bail|nullable|string|max:50',
            'payment_date' => 'bail|nullable|date_format:Y-m-d',
            'return_payment_method' => 'bail|nullable|string|max:50',
            'return_payment_date' => 'bail|nullable|date_format:Y-m-d',
        ]);
        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->first();
    }
}
