<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailCollateralResource;
use App\Models\Collateral;
use App\Models\CollateralItem;
use App\Models\Document;
use App\Models\Member;
use App\Models\Site;
use App\Models\TubeContentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CollateralManagementController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        try {
            $collaterals = Collateral::when($user->level != 0, function ($q) use ($sites) {
                $q->whereIn('site_id', $sites);
            })
            ->orderByDesc('date')
            ->orderByDesc('created_at');
            if ($r->filled('paginate')) {
                $transactions = $collaterals->paginate($r->integer('paginate'));
            } else {
                $transactions = $collaterals->get();
            }
            return DetailCollateralResource::collection($transactions);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function create(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'member' => 'bail|required|exists:members,uid',
            'date' => 'bail|required|date_format:Y-m-d',
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
            'document' => 'bail|nullable|file|mimes:pdf|max:2048',
            'collateral_audit' => 'bail|nullable|string|max:100',
            'return_audit' => 'bail|nullable|string|max:100',
            'items' => 'bail|required|array',
            'items.*.tube_content_type' => 'bail|required|exists:tube_content_types,uid',
            'items.*.klep_condition' => 'bail|nullable|string',
            'items.*.tube_cap' => 'bail|nullable|string',
            'items.*.tube_quantity' => 'bail|required|integer|min:1',
            'items.*.nominal' => 'bail|required|integer|min:0',
        ],[
            'site.required' => 'Tentukan cabang',
            'member.required' => 'Tentukan member',
            'date.required' => 'Tentukan tanggal',
            'date.date_format' => 'Format tanggal tidak valid',
            'type.required' => 'Tentukan jenis dokumen',
            'type.in' => 'Jenis dokumen tidak valid',
            'pic.required' => 'Masukkan PIC',
            'pic.max' => 'PIC maksimal 100 karakter',
            'document_number.max' => 'Nomor surat maksimal 50 karakter',
            'member_name.required' => 'Masukkan nama member',
            'member_name.max' => 'Nama member maksimal 100 karakter',
            'member_address.max' => 'Alamat member maksimal 200 karakter',
            'signatory_status.max' => 'Status penandatangan maksimal 50 karakter',
            'company_name.max' => 'Nama usaha maksimal 100 karakter',
            'contact_person.max' => 'Kontak maksimal 100 karakter',
            'payment_method.max' => 'Metode pembayaran maksimal 50 karakter',
            'payment_date.date_format' => 'Format tanggal pembayaran tidak valid',
            'return_payment_method.max' => 'Metode pembayaran pengembalian maksimal 50 karakter',
            'return_payment_date.date_format' => 'Format tanggal pembayaran pengembalian tidak valid',
            'document.mimes' => 'Dokumen harus berupa file PDF',
            'document.max' => 'Ukuran dokumen maksimal 2MB',
            'collateral_audit.max' => 'Audit jaminan maksimal 100 karakter',
            'return_audit.max' => 'Audit pengembalian maksimal 100 karakter',
            'items.required' => 'Masukkan jaminan',
            'items.*.tube_content_type.required' => 'Tentukan jenis isi tabung',
            'items.*.tube_quantity.required' => 'Masukkan jumlah tabung',
            'items.*.tube_quantity.integer' => 'Jumlah tabung harus berupa angka',
            'items.*.tube_quantity.min' => 'Jumlah tabung minimal 1',
            'items.*.nominal.required' => 'Masukkan nominal jaminan',
            'items.*.nominal.integer' => 'Nominal jaminan harus berupa angka',
            'items.*.nominal.min' => 'Nominal jaminan minimal 0',
        ]);

        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->first();
        $member = Member::where('uid', $r->input('member'))->first();

        DB::beginTransaction();
        try {
            $collateral = new Collateral;
            $collateral->site()->associate($site);
            $collateral->member()->associate($member);
            $collateral->date = $r->input('date');
            $collateral->type = $r->input('type');
            $collateral->pic = $r->input('pic');
            $collateral->document_number = $r->input('document_number');
            $collateral->member_name = $r->input('member_name');
            $collateral->member_address = $r->input('member_address');
            $collateral->signatory_status = $r->input('signatory_status');
            $collateral->company_name = $r->input('company_name');
            $collateral->contact_person = $r->input('contact_person');
            if ($r->input('type') == 'collateral') {
                $collateral->payment_method = $r->input('payment_method');
                $collateral->payment_date = $r->input('payment_date');
                $collateral->collateral_audit = $r->input('collateral_audit');
            }
            if ($r->input('type') == 'return') {
                $collateral->return_payment_method = $r->input('return_payment_method');
                $collateral->return_payment_date = $r->input('return_payment_date');
                $collateral->return_audit = $r->input('return_audit');
            }
            $collateral->save();

            if ($r->hasFile('document')) {
                $file = $r->file('document');
                $path = Storage::disk('documents')->put('collaterals', $file);
                $document = new Document;
                $document->documentable()->associate($collateral);
                $document->type = 'collateral';
                $document->path = $path;
                $document->save();
            }

            foreach ($r->input('items') as $item) {
                $tubeContentType = TubeContentType::where('uid', $item['tube_content_type'])->first();
                $collateralItem = new CollateralItem;
                $collateralItem->collateral()->associate($collateral);
                $collateralItem->tubeContentType()->associate($tubeContentType);
                $collateralItem->klep_condition = $item['klep_condition'];
                $collateralItem->tube_cap = $item['tube_cap'];
                $collateralItem->tube_quantity = $item['tube_quantity'];
                $collateralItem->nominal = $item['nominal'];
                $collateralItem->save();
            }

            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function addItem(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');

        $collateral = Collateral::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();

        $r->validate([
            'tube_content_type' => 'bail|required|exists:tube_content_types,uid',
            'klep_condition' => 'bail|nullable|string',
            'tube_cap' => 'bail|nullable|string',
            'tube_quantity' => 'bail|required|integer|min:1',
            'nominal' => 'bail|required|integer|min:0',
        ],[
            'tube_content_type.required' => 'Tentukan jenis isi tabung',
            'tube_quantity.required' => 'Masukkan jumlah tabung',
            'tube_quantity.integer' => 'Jumlah tabung harus berupa angka',
            'tube_quantity.min' => 'Jumlah tabung minimal 1',
            'nominal.required' => 'Masukkan nominal jaminan',
            'nominal.integer' => 'Nominal jaminan harus berupa angka',
            'nominal.min' => 'Nominal jaminan minimal 0',
        ]);

        DB::beginTransaction();
        try {
            $tubeContentType = TubeContentType::where('uid', $r->input('tube_content_type'))->first();
            $collateralItem = new CollateralItem;
            $collateralItem->collateral()->associate($collateral);
            $collateralItem->tubeContentType()->associate($tubeContentType);
            $collateralItem->klep_condition = $r->input('klep_condition');
            $collateralItem->tube_cap = $r->input('tube_cap');
            $collateralItem->tube_quantity = $r->input('tube_quantity');
            $collateralItem->nominal = $r->input('nominal');
            $collateralItem->save();
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
